<?php

namespace EasyAI\LaravelAI\Drivers;

use EasyAI\LaravelAI\Exceptions\ConnectionException;
use EasyAI\LaravelAI\Exceptions\ProviderException;
use EasyAI\LaravelAI\Support\UsageLogger;
use Illuminate\Support\Facades\Http;

/**
 * Together AI exposes an OpenAI-compatible /chat/completions endpoint, so
 * chat/stream/health/models are inherited as-is. This subclass only adds
 * Together's image-generation endpoint (FLUX models), which the WordPress
 * plugin routes /image and /img chat commands to.
 */
class TogetherDriver extends OpenAIDriver
{
    public function getProviderName(): string
    {
        return 'together';
    }

    public function generateImage(string $prompt): string
    {
        $url   = rtrim($this->config['url'], '/') . '/images/generations';
        $model = $this->config['image_model'] ?? 'black-forest-labs/FLUX.1-schnell';
        $size  = explode('x', $this->config['image_size'] ?? '1024x1024');
        $width  = (int) $size[0];
        $height = (int) ($size[1] ?? 1024);

        try {
            $response = Http::timeout($this->getTimeout())
                ->withToken($this->config['api_key'])
                ->post($url, [
                    // Falls back to the paid FLUX.1-schnell, not the "-Free"
                    // promotional endpoint — see the matching note in config/ai.php.
                    'model'  => $model,
                    'prompt' => $prompt,
                    'width'  => $width,
                    'height' => $height,
                    'steps'  => (int) ($this->config['image_steps'] ?? 4),
                    'n'      => 1,
                ]);

            if (!$response->successful()) {
                throw new ProviderException(
                    "together image error: {$response->status()} - {$response->body()}",
                    'together',
                    ['status' => $response->status()],
                    $response->status()
                );
            }

            $imageUrl = $response->json('data.0.url');
            if (!$imageUrl) {
                throw new ProviderException('together image error: no URL in response', 'together');
            }

            // Together prices FLUX per megapixel, not per image — pass the
            // actual dimensions used so UsageLogger can apply a 'per_mp' rate.
            UsageLogger::log('together', $model, 'image', [
                'image_count' => 1,
                'megapixels'  => ($width * $height) / 1_000_000,
            ]);

            return $imageUrl;
        } catch (ProviderException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ConnectionException("together image connection failed: {$e->getMessage()}", 'together', [], 0, $e);
        }
    }
}
