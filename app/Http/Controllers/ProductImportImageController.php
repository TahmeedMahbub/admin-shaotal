<?php

namespace App\Http\Controllers;

use App\Exceptions\ImageUploadException;
use App\Models\ProductImportItem;
use App\Services\CloudinaryImageService;
use App\Services\ProductImport\BackgroundCleaner;
use App\Services\ProductImport\GeminiImageClient;
use App\Services\ProductImport\ImportException;
use App\Services\ProductImport\PageFetcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Source-image handling for the import review page. Nothing reaches Cloudinary (or the product) until the admin
 * explicitly uses an original or approves a generated preview. Only URLs the extraction found can be fetched.
 */
class ProductImportImageController extends Controller
{
    private const FOLDER = 'products';

    public function __construct(private PageFetcher $fetcher, private CloudinaryImageService $cloudinary) {}

    /** Use the source photo unchanged: download it and upload it to Cloudinary. */
    public function original(Request $request, ProductImportItem $item): JsonResponse
    {
        $source = $this->source($request, $item);

        return $this->respond(function () use ($item, $source) {
            $img = $this->fetcher->fetchImage($source, $item->source_url);
            $url = $this->toCloudinary($img['bytes']);
            $this->record($item, $source, $url, 'original');

            return ['url' => $url];
        });
    }

    /** Render a preview (AI or quick clean). Stored privately until approved. */
    public function generate(Request $request, ProductImportItem $item): JsonResponse
    {
        $source = $this->source($request, $item);
        $mode = $request->validate(['mode' => ['required', 'in:gemini,local']])['mode'];

        return $this->respond(function () use ($item, $source, $mode) {
            $img = $this->fetcher->fetchImage($source, $item->source_url);
            $jpeg = $mode === 'gemini' ? app(GeminiImageClient::class)->restyle($img['bytes'], $img['mime']) : app(BackgroundCleaner::class)->clean($img['bytes']);

            $this->pruneOldPreviews($item);

            $id = (string) Str::uuid();
            Storage::disk('local')->put("import-previews/{$item->id}/$id.jpg", $jpeg);
            Storage::disk('local')->put("import-previews/{$item->id}/$id.json", json_encode(['source' => $source, 'mode' => $mode, 'admin' => auth('admin')->id()]));

            return ['preview_id' => $id, 'preview_url' => route('product-imports.images.preview', [$item, $id]), 'mode' => $mode];
        });
    }

    /** Upload an approved preview to Cloudinary. */
    public function approve(Request $request, ProductImportItem $item): JsonResponse
    {
        $id = $this->previewId($request->input('preview_id'));
        $disk = Storage::disk('local');
        $path = "import-previews/{$item->id}/$id";
        abort_unless($disk->exists("$path.jpg") && $disk->exists("$path.json"), 404);
        $meta = json_decode($disk->get("$path.json"), true);
        abort_unless(is_array($meta) && ($meta['admin'] ?? null) === auth('admin')->id(), 404);

        return $this->respond(function () use ($item, $disk, $path, $meta) {
            $url = $this->toCloudinary($disk->get("$path.jpg"));
            $this->record($item, $meta['source'], $url, $meta['mode']);
            $disk->delete(["$path.jpg", "$path.json"]);

            return ['url' => $url];
        });
    }

    public function preview(ProductImportItem $item, string $id)
    {
        $id = $this->previewId($id);
        $path = "import-previews/{$item->id}/$id.jpg";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=600']);
    }

    /** Previews nobody approved are removed after a day so they do not pile up. */
    private function pruneOldPreviews(ProductImportItem $item): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files("import-previews/{$item->id}") as $file) {
            if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }

    private function source(Request $request, ProductImportItem $item): string
    {
        $url = (string) $request->input('url');
        abort_unless($url !== '' && in_array($url, $item->source_images ?? [], true), 422, 'That image is not one of the extracted source images.');

        return $url;
    }

    private function previewId(mixed $id): string
    {
        abort_unless(is_string($id) && preg_match('/^[a-f0-9\-]{36}$/', $id), 404);

        return $id;
    }

    private function toCloudinary(string $bytes): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'imp');
        try {
            file_put_contents($tmp, $bytes);

            return $this->cloudinary->uploadFile($tmp, self::FOLDER);
        } finally {
            @unlink($tmp);
        }
    }

    private function record(ProductImportItem $item, string $source, string $url, string $mode): void
    {
        $item->update(['generated_images' => array_merge($item->generated_images ?? [], [['source' => $source, 'url' => $url, 'mode' => $mode, 'at' => now()->toIso8601String()]])]);
    }

    private function respond(callable $work): JsonResponse
    {
        try {
            return response()->json($work());
        } catch (ImportException|ImageUploadException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
