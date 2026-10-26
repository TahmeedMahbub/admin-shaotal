<?php

namespace App\Services;

use App\Exceptions\ImageUploadException;
use Closure;
use Cloudinary;
use Cloudinary\Uploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Uploads images to Cloudinary using the installed cloudinary/cloudinary_php v1.x API
 * (global `Cloudinary::config()` + `Cloudinary\Uploader::upload()`).
 */
class CloudinaryImageService
{
    /** @param  Closure|null  $uploader  fn (string $path, array $options): array — replaced in tests. */
    public function __construct(private ?Closure $uploader = null) {}

    /**
     * Upload an image and return only its secure HTTPS URL.
     *
     * @throws ImageUploadException with a user-safe message
     */
    public function upload(UploadedFile $file, ?string $folder = null): string
    {
        $validator = Validator::make(['image' => $file], [
            'image' => ['required', 'file', 'image', 'mimes:'.implode(',', config('cloudinary.allowed_mimes')), 'max:'.config('cloudinary.max_kb')],
        ]);

        if ($validator->fails()) {
            throw new ImageUploadException($validator->errors()->first('image'));
        }

        return $this->send($file->getRealPath(), $folder);
    }

    /**
     * Upload a local image file (e.g. a downloaded or generated one) and return only its secure HTTPS URL.
     *
     * @throws ImageUploadException with a user-safe message
     */
    public function uploadFile(string $path, ?string $folder = null): string
    {
        $mime = is_file($path) ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
        $allowed = array_map(fn ($e) => $e === 'jpg' ? 'image/jpeg' : 'image/'.$e, config('cloudinary.allowed_mimes'));
        if (! in_array($mime, $allowed, true) || @getimagesize($path) === false) {
            throw new ImageUploadException('The file is not a JPG, PNG, WEBP or GIF image.');
        }
        if (filesize($path) > config('cloudinary.max_kb') * 1024) {
            throw new ImageUploadException('The image is larger than '.(config('cloudinary.max_kb') / 1024).' MB.');
        }

        return $this->send($path, $folder);
    }

    private function send(string $path, ?string $folder): string
    {
        if (! config('cloudinary.cloud_name') || ! config('cloudinary.api_key') || ! config('cloudinary.api_secret')) {
            throw new ImageUploadException('Image upload is not configured.');
        }

        $options = [
            'folder' => trim(config('cloudinary.root_folder').'/'.trim((string) $folder, '/'), '/'),
            'resource_type' => 'image',
        ];

        try {
            $result = ($this->uploader ?? $this->sdkUploader())($path, $options);
        } catch (Throwable $e) {
            // Log the class and message only; the SDK never includes the secret, and we never echo it to users.
            Log::error('Cloudinary upload failed', ['exception' => $e::class, 'message' => $e->getMessage()]);

            throw new ImageUploadException('The image could not be uploaded. Please try again.');
        }

        $url = is_array($result) ? ($result['secure_url'] ?? null) : null;

        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            Log::error('Cloudinary upload returned no secure_url');

            throw new ImageUploadException('The image could not be uploaded. Please try again.');
        }

        return $url;
    }

    private function sdkUploader(): Closure
    {
        return function (string $path, array $options): array {
            Cloudinary::config([
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
                'secure' => true,
            ]);

            return (array) Uploader::upload($path, $options);
        };
    }
}
