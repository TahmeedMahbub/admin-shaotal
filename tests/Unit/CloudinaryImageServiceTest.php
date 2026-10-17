<?php

namespace Tests\Unit;

use App\Exceptions\ImageUploadException;
use App\Services\CloudinaryImageService;
use Cloudinary\Uploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CloudinaryImageServiceTest extends TestCase
{
    private function configured(): void
    {
        config(['cloudinary.cloud_name' => 'demo', 'cloudinary.api_key' => 'key', 'cloudinary.api_secret' => 'super-secret', 'cloudinary.root_folder' => 'shaotal']);
    }

    public function test_installed_sdk_exposes_the_api_the_service_calls(): void
    {
        // Guards against SDK major-version drift (the v2 classes do not exist in the installed v1 package).
        $this->assertTrue(class_exists(\Cloudinary::class), 'Cloudinary class missing');
        $this->assertTrue(method_exists(\Cloudinary::class, 'config'));
        $this->assertTrue(class_exists(Uploader::class), 'Cloudinary\\Uploader missing');
        $this->assertTrue(method_exists(Uploader::class, 'upload'));
    }

    public function test_returns_only_the_secure_url_and_uses_the_folder(): void
    {
        $this->configured();
        $seen = null;
        $service = new CloudinaryImageService(function (string $path, array $options) use (&$seen) {
            $seen = $options;

            return ['secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/shaotal/products/a.jpg', 'url' => 'http://x', 'api_key' => 'leak', 'public_id' => 'p'];
        });

        $url = $service->upload(UploadedFile::fake()->image('a.jpg'), 'products');

        $this->assertSame('https://res.cloudinary.com/demo/image/upload/v1/shaotal/products/a.jpg', $url);
        $this->assertSame('shaotal/products', $seen['folder']);
    }

    public function test_rejects_non_image_files(): void
    {
        $this->configured();
        $this->expectException(ImageUploadException::class);
        (new CloudinaryImageService(fn () => []))->upload(UploadedFile::fake()->create('note.txt', 10, 'text/plain'), 'products');
    }

    public function test_rejects_oversized_images(): void
    {
        $this->configured();
        $this->expectException(ImageUploadException::class);
        (new CloudinaryImageService(fn () => []))->upload(UploadedFile::fake()->image('big.jpg')->size(config('cloudinary.max_kb') + 1), 'products');
    }

    public function test_fails_safely_when_not_configured(): void
    {
        config(['cloudinary.cloud_name' => null, 'cloudinary.api_key' => null, 'cloudinary.api_secret' => null]);
        $this->expectExceptionMessage('Image upload is not configured.');
        (new CloudinaryImageService(fn () => []))->upload(UploadedFile::fake()->image('a.jpg'), 'products');
    }

    public function test_sdk_failures_become_a_safe_message(): void
    {
        $this->configured();
        Log::spy();

        try {
            (new CloudinaryImageService(fn () => throw new \RuntimeException('boom with key=key')))->upload(UploadedFile::fake()->image('a.jpg'), 'products');
            $this->fail('Expected ImageUploadException');
        } catch (ImageUploadException $e) {
            $this->assertSame('The image could not be uploaded. Please try again.', $e->getMessage());
            $this->assertStringNotContainsString('super-secret', $e->getMessage());
        }
    }

    public function test_response_without_https_secure_url_is_rejected(): void
    {
        $this->configured();
        $this->expectException(ImageUploadException::class);
        (new CloudinaryImageService(fn () => ['secure_url' => 'http://insecure/a.jpg']))->upload(UploadedFile::fake()->image('a.jpg'), 'products');
    }
}
