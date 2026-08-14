<?php

namespace EasyAI\LaravelAI\Tests\Feature;

use EasyAI\LaravelAI\Exceptions\ProviderException;
use EasyAI\LaravelAI\Facades\AI;
use EasyAI\LaravelAI\Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * ->transcribe() / ->textToSpeech() — OpenAI's real /audio/transcriptions
 * (multipart upload) and /audio/speech (binary response) endpoints,
 * verified against OpenAI's own OpenAPI spec, and against Groq's/
 * Together's own docs for which of the two each inherited backend
 * genuinely supports (not assumed from "OpenAI-compatible chat").
 */
class AudioTest extends TestCase
{
    private function tempAudioFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'laravelai_audio_test_') . '.mp3';
        file_put_contents($path, 'fake mp3 bytes');
        return $path;
    }

    public function test_openai_transcribe_returns_text(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'hello world']),
        ]);

        $path = $this->tempAudioFile();
        $text = AI::provider('openai')->transcribe($path);
        @unlink($path);

        $this->assertSame('hello world', $text);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/audio/transcriptions');
    }

    public function test_openai_transcribe_missing_file_throws_before_any_request(): void
    {
        Http::fake();

        $this->expectException(\InvalidArgumentException::class);

        AI::provider('openai')->transcribe('/nonexistent/path/audio.mp3');

        Http::assertNothingSent();
    }

    public function test_openai_transcribe_error_response_throws_provider_exception(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/transcriptions' => Http::response(['error' => 'bad audio'], 400),
        ]);

        $path = $this->tempAudioFile();

        try {
            $this->expectException(ProviderException::class);
            AI::provider('openai')->transcribe($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_openai_text_to_speech_returns_raw_audio_bytes(): void
    {
        Http::fake([
            'api.openai.com/v1/audio/speech' => Http::response('FAKE_MP3_BINARY_DATA'),
        ]);

        $audio = AI::provider('openai')->textToSpeech('Hello there');

        $this->assertSame('FAKE_MP3_BINARY_DATA', $audio);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $body['model'] === 'tts-1'
                && $body['input'] === 'Hello there'
                && $body['voice'] === 'alloy'
                && $body['response_format'] === 'mp3';
        });
    }

    public function test_openai_text_to_speech_honors_options(): void
    {
        Http::fake(['api.openai.com/v1/audio/speech' => Http::response('bytes')]);

        AI::provider('openai')->textToSpeech('Hi', ['voice' => 'nova', 'model' => 'gpt-4o-mini-tts', 'format' => 'wav']);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $body['voice'] === 'nova' && $body['model'] === 'gpt-4o-mini-tts' && $body['response_format'] === 'wav';
        });
    }

    public function test_groq_inherits_transcribe_and_tts_from_openai_driver(): void
    {
        Http::fake([
            'api.groq.com/*/audio/transcriptions' => Http::response(['text' => 'fast transcription']),
        ]);

        $path = $this->tempAudioFile();
        $text = AI::provider('groq')->transcribe($path, ['model' => 'whisper-large-v3-turbo']);
        @unlink($path);

        $this->assertSame('fast transcription', $text);
    }
}
