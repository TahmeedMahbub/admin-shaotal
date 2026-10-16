<?php

namespace App\Http\Controllers;

use App\Exceptions\ImageUploadException;
use App\Services\CloudinaryImageService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

abstract class Controller
{
    protected function perPage(): int
    {
        return config('admin.per_page');
    }

    /** Upload the file in $field (if any) to Cloudinary and return its URL, else return $current. */
    protected function uploadImage(Request $request, string $field, string $folder, ?string $current = null): ?string
    {
        $file = $request->file($field);

        return $file ? $this->upload($file, $folder, $field) : $current;
    }

    protected function upload($file, string $folder, string $field = 'image'): string
    {
        try {
            return app(CloudinaryImageService::class)->upload($file, $folder);
        } catch (ImageUploadException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }

    /** Slug from the supplied value or the name, made unique in $table when derived. */
    protected function slugFor(string $table, ?string $slug, string $name, ?int $ignoreId = null, string $column = 'slug'): string
    {
        if ($slug) {
            return Str::slug($slug);
        }

        $base = $candidate = Str::slug($name) ?: 'item';
        for ($i = 2; DB::table($table)->where($column, $candidate)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists(); $i++) {
            $candidate = $base.'-'.$i;
        }

        return $candidate;
    }

    /** Run a delete and turn foreign-key failures into a friendly flash message. */
    protected function destroyWith(callable $delete, string $redirectRoute, string $what): RedirectResponse
    {
        try {
            $delete();
        } catch (QueryException $e) {
            report($e);

            return redirect()->route($redirectRoute)->with('error', "This {$what} is in use and cannot be deleted. Deactivate it instead.");
        }

        return redirect()->route($redirectRoute)->with('success', ucfirst($what).' deleted.');
    }
}
