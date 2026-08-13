<?php

namespace EasyAI\LaravelAI\Drivers;

/**
 * Groq (groq.com) exposes an OpenAI-compatible /chat/completions endpoint
 * (LPU-hosted open models — Llama, etc.) — chat/stream/tool-calling/health/
 * models are all inherited from OpenAIDriver as-is, same minimal pattern as
 * DeepSeekDriver. Groq's /models endpoint also mirrors OpenAI's
 * {"data": [{"id": ...}]} shape, so no override is needed there either.
 */
class GroqDriver extends OpenAIDriver
{
    public function getProviderName(): string
    {
        return 'groq';
    }
}
