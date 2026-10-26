<?php

namespace App\Services\ProductImport;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Asks Gemini to re-render a product photo on the Shaotal beige background, using the original photo as the reference. */
class GeminiImageClient
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public const PROMPT = 'Use the attached photo as the exact reference. Produce a clean e-commerce product photo of the same product on a plain, '
        .'seamless warm beige studio background, colour #F5EDE3, soft even lighting and a very light natural shadow, centred with generous margin, square 1:1. '
        .'Keep the product (and any person wearing it) identical to the reference: the same colour, fabric texture, pattern, stitching, logos, proportions, fit and details. '
        .'Do not add, remove, restyle or recolour anything on the product, do not add text, props or watermarks. Only the background and lighting change.';

    /** @return string JPEG bytes */
    public function restyle(string $bytes, string $mime): string
    {
        $key = config('services.gemini.key');
        $model = (string) config('services.gemini.image_model');
        if (! $key) {
            throw new ImportException('Gemini is not configured. Add GEMINI_API_KEY to .env.', false);
        }
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $model)) {
            throw new ImportException('GEMINI_IMAGE_MODEL is not a valid model name.', false);
        }
        @set_time_limit(180);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])->acceptJson()->timeout(150)->connectTimeout(10)
                ->post(self::ENDPOINT.$model.':generateContent', [
                    'contents' => [['parts' => [
                        ['text' => self::PROMPT],
                        ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]],
                    ]]],
                    'generationConfig' => ['responseModalities' => ['TEXT', 'IMAGE']],
                ]);
        } catch (Throwable $e) {
            Log::warning('Gemini request failed', ['exception' => $e::class]);
            throw new ImportException('Could not reach Gemini ('.class_basename($e).'). Try again.');
        }

        if (! $response->successful()) {
            // Only the API's own message is shown; the key is never part of it.
            $msg = (string) ($response->json('error.message') ?? 'HTTP '.$response->status());
            Log::warning('Gemini returned an error', ['status' => $response->status()]);
            throw new ImportException('Gemini refused the request: '.mb_substr($msg, 0, 200), $response->status() === 429 || $response->status() >= 500);
        }

        foreach ((array) $response->json('candidates.0.content.parts', []) as $part) {
            $data = $part['inlineData']['data'] ?? $part['inline_data']['data'] ?? null;
            if (is_string($data) && ($raw = base64_decode($data, true)) !== false && @getimagesizefromstring($raw) !== false) {
                return $this->toJpeg($raw);
            }
        }

        $why = (string) ($response->json('candidates.0.finishReason') ?? $response->json('promptFeedback.blockReason') ?? 'no image returned');
        throw new ImportException('Gemini did not return an image ('.mb_substr($why, 0, 80).'). Try again or use the original.', false);
    }

    private function toJpeg(string $raw): string
    {
        $img = imagecreatefromstring($raw);
        $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($flat, 0, 0, 0xF5EDE3);
        imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        ob_start();
        imagejpeg($flat, null, 92);

        return (string) ob_get_clean();
    }
}
