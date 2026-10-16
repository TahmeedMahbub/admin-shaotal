<?php

namespace Tests\Unit;

use App\Exceptions\ImageUploadException;
use App\Services\CloudinaryImageService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CloudinaryImageServiceTest extends TestCase
{
    private function configured(): void
    {
        config(['cloudinary.cloud_name' => 'x', 'cloudinary.api_key' => 'y', 'cloudinary.api_secret' => 'z']);
    }

    public function test_rejects_non_image_files(): void
    {
        $this->configured();
        $this->expectException(ImageUploadException::class);
        (new CloudinaryImageService)->upload(UploadedFile::fake()->create('note.txt', 10, 'text/plain'), 'products');
    }

    public function test_rejects_oversized_images(): void
    {
        $this->configured();
        $this->expectException(ImageUploadException::class);
        (new CloudinaryImageService)->upload(UploadedFile::fake()->image('big.jpg')->size(config('cloudinary.max_kb') + 1), 'products');
    }

    public function test_fails_safely_when_not_configured(): void
    {
        config(['cloudinary.cloud_name' => null, 'cloudinary.api_key' => null, 'cloudinary.api_secret' => null]);
        $this->expectExceptionMessage('Image upload is not configured.');
        (new CloudinaryImageService)->upload(UploadedFile::fake()->image('a.jpg'), 'products');
    }

    public function test_upload_errors_never_leak_credentials(): void
    {
        config(['cloudinary.cloud_name' => 'invalid-cloud', 'cloudinary.api_key' => '1', 'cloudinary.api_secret' => 'super-secret']);

        try {
            (new CloudinaryImageService)->upload(UploadedFile::fake()->image('a.jpg'), 'products');
            $this->fail('Expected an upload failure.');
        } catch (ImageUploadException $e) {
            $this->assertStringNotContainsString('super-secret', $e->getMessage());
        }
    }
}
