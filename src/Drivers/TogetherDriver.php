<?php

namespace EasyAI\LaravelAI\Drivers;

use EasyAI\LaravelAI\Exceptions\ConnectionException;
use EasyAI\LaravelAI\Exceptions\ProviderException;
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
        $url = rtrim($this->config['url'], '/') . '/images/generations';

        try {
            $response = Http::timeout($this->getTimeout())
                ->withToken($this->config['api_key'])
                ->post($url, [
                    // Falls back to the paid FLUX.1-schnell, not the "-Free"
                    // promotional endpoint — see the matching note in config/ai.php.
                    'model'  => $this->config['image_model'] ?? 'black-forest-labs/FLUX.1-schnell',
                    'prompt' => $prompt,
                    'width'  => (int) explode('x', $this->config['image_size'] ?? '1024x1024')[0],
                    'height' => (int) (explode('x', $this->config['image_size'] ?? '1024x1024')[1] ?? 1024),
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

            $url = $response->json('data.0.url');
            if (!$url) {
                throw new ProviderException('together image error: no URL in response', 'together');
            }

            return $url;
        } catch (ProviderException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ConnectionException("together image connection failed: {$e->getMessage()}", 'together', [], 0, $e);
        }
    }
}
