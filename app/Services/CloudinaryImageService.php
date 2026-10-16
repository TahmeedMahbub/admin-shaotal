<?php

namespace App\Services;

use App\Exceptions\ImageUploadException;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CloudinaryImageService
{
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

        if (! config('cloudinary.cloud_name') || ! config('cloudinary.api_key') || ! config('cloudinary.api_secret')) {
            throw new ImageUploadException('Image upload is not configured.');
        }

        try {
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => config('cloudinary.cloud_name'),
                    'api_key' => config('cloudinary.api_key'),
                    'api_secret' => config('cloudinary.api_secret'),
                ],
                'url' => ['secure' => true],
            ]);

            $result = (new UploadApi)->upload($file->getRealPath(), [
                'folder' => trim(config('cloudinary.root_folder').'/'.trim((string) $folder, '/'), '/'),
                'resource_type' => 'image',
            ]);
        } catch (Throwable $e) {
            Log::error('Cloudinary upload failed', ['exception' => $e::class, 'message' => $e->getMessage()]);

            throw new ImageUploadException('The image could not be uploaded. Please try again.');
        }

        $url = $result['secure_url'] ?? null;

        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new ImageUploadException('The image could not be uploaded. Please try again.');
        }

        return $url;
    }
}
