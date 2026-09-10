<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiClient
{
    /**
     * Generate a reply via Gemini generateContent.
     *
     * @param  list<array{role: string, text: string}>  $history
     * @return array{reply: string, model: string|null, usage: array<string, mixed>|null}
     */
    public function generate(string $message, string $systemInstruction = '', array $history = []): array
    {
        $apiKey = trim((string) config('services.gemini.key'));
        $model = (string) config('services.gemini.model', 'gemini-3.6-flash');
        $baseUrl = rtrim((string) config('services.gemini.base_url'), '/');
        $timeout = (int) config('services.gemini.timeout', 45);

        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY n’est pas configurée.');
        }

        $contents = [];

        foreach ($history as $turn) {
            $role = ($turn['role'] ?? '') === 'model' ? 'model' : 'user';
            $text = trim((string) ($turn['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $text]],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $message]],
        ];

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 1024,
            ],
        ];

        if ($systemInstruction !== '') {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        $url = "{$baseUrl}/models/{$model}:generateContent";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
            ])
                ->timeout($timeout)
                ->asJson()
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            Log::error('Gemini connection failed', [
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException('Impossible de joindre le service d’assistance pour le moment.');
        }

        $json = $response->json();
        $json = is_array($json) ? $json : [];

        if (! $response->successful()) {
            $apiMessage = data_get($json, 'error.message');

            Log::error('Gemini API error', [
                'status' => $response->status(),
                'message' => $apiMessage,
            ]);

            throw new RuntimeException(
                is_string($apiMessage) && $apiMessage !== ''
                    ? 'Assistance IA: '.$apiMessage
                    : 'Le service d’assistance a renvoyé une erreur. Réessayez plus tard.'
            );
        }

        return [
            'reply' => $this->extractText($json),
            'model' => $model,
            'usage' => is_array($json['usageMetadata'] ?? null) ? $json['usageMetadata'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function extractText(array $response): string
    {
        $parts = data_get($response, 'candidates.0.content.parts');

        if (! is_array($parts)) {
            return '';
        }

        $chunks = [];

        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text'])) {
                $chunks[] = (string) $part['text'];
            }
        }

        return trim(implode('', $chunks));
    }
}
