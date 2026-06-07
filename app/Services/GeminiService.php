<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $primaryModel;
    protected string $fallbackModel;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY', '');
        $this->primaryModel = env('GEMINI_PRIMARY_MODEL', 'gemini-3.5-flash');
        $this->fallbackModel = env('GEMINI_FALLBACK_MODEL', 'gemini-1.5-flash');
    }

    /**
     * Generate content from Gemini API with retries and fallback model support.
     *
     * @param string $prompt
     * @param string|null $modelOverride
     * @return string
     * @throws \Exception
     */
    public function generateContent(string $prompt, array $mediaParts = [], ?string $modelOverride = null): string
    {
        if (!$this->apiKey) {
            throw new \Exception('Gemini API key is missing. Please check your .env file.');
        }

        $model = $modelOverride ?? $this->primaryModel;

        try {
            return $this->callApiWithRetry($model, $prompt, $mediaParts);
        } catch (\Throwable $e) {
            // If we used a specific override or if primary and fallback are the same, don't try fallback
            if ($modelOverride || $this->primaryModel === $this->fallbackModel) {
                throw new \Exception($e->getMessage(), $e->getCode(), $e);
            }

            Log::warning("Gemini primary model {$this->primaryModel} failed. Attempting fallback {$this->fallbackModel}. Error: " . $e->getMessage());

            try {
                return $this->callApiWithRetry($this->fallbackModel, $prompt, $mediaParts);
            } catch (\Throwable $fallbackException) {
                throw new \Exception("Both primary ({$this->primaryModel}) and fallback ({$this->fallbackModel}) models failed.\nPrimary error: {$e->getMessage()}\nFallback error: {$fallbackException->getMessage()}");
            }
        }
    }

    /**
     * Execute HTTP POST call to Gemini API with Laravel HTTP Client retry mechanism.
     *
     * @param string $model
     * @param string $prompt
     * @param array $mediaParts
     * @return string
     * @throws \Throwable
     */
    protected function callApiWithRetry(string $model, string $prompt, array $mediaParts = []): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->apiKey}";

        $parts = [];
        foreach ($mediaParts as $media) {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => $media['mimeType'],
                    'data' => $media['data']
                ]
            ];
        }
        $parts[] = ['text' => $prompt];

        // Retry up to 3 times, waiting 1000ms between attempts, retrying on connection or 5xx/429 errors
        $response = Http::timeout(40)
            ->retry(3, 1000, function (\Throwable $exception) {
                if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                    return true;
                }
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    $status = $exception->response->status();
                    return $status === 429 || $status >= 500;
                }
                return false;
            })
            ->post($url, [
                'contents' => [['parts' => $parts]]
            ])
            ->throw(); // Enable throwing exceptions on non-2xx responses to trigger the retry mechanism

        $text = $response->json('candidates.0.content.parts.0.text');

        if (is_null($text) || trim($text) === '') {
            throw new \Exception("Empty response from Gemini API.");
        }

        return $text;
    }
}
