<?php

namespace EasyAI\LaravelAI\Drivers;

/**
 * Any OpenAI-compatible endpoint — LM Studio, vLLM, OpenRouter, an in-house
 * gateway — reusing the OpenAI driver's request/response shape entirely.
 * Configured per-entry under config('ai.custom_providers'); the entry's
 * array key becomes this driver's provider name (e.g. "custom:lmstudio").
 */
class CustomDriver extends OpenAIDriver
{
    private string $name;

    public function __construct(array $config, string $name = 'custom')
    {
        parent::__construct($config);
        $this->name = $name;
    }

    public function getProviderName(): string
    {
        return $this->name;
    }
}
