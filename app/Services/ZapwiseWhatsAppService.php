<?php

namespace App\Services;

use App\Support\Phone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ZapwiseWhatsAppService
{
    /**
     * POST https://api.zapwize.com/v1/whatsapp/message
     * Authorization: Bearer {token}
     * Content-Type: application/json
     * { "chatid": "<numéro utilisateur>", "content": { "text": "…" } }
     */
    public function sendText(string $phone, string $text): void
    {
        $token = trim((string) config('services.zapwize.key'), " \t\n\r\0\x0B\"'");
        $url = (string) config('services.zapwize.url');

        if ($token === '' || $url === '') {
            throw ValidationException::withMessages([
                'phone' => 'L’envoi WhatsApp n’est pas configuré.',
            ]);
        }

        $chatId = Phone::toWhatsAppChatId($phone);

        if ($chatId === '') {
            throw ValidationException::withMessages([
                'phone' => 'Indiquez un numéro WhatsApp valide.',
            ]);
        }

        $payload = [
            'chatid' => $chatId,
            'content' => [
                'text' => $text,
            ],
        ];

        Log::info('Zapwize WhatsApp send', [
            'chatid' => $chatId,
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ])
                ->withOptions([
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ],
                ])
                ->asJson()
                ->timeout(20)
                ->post($url, $payload);
        } catch (ConnectionException) {
            Log::error('Zapwize WhatsApp connection failed', [
                'chatid' => $chatId,
            ]);

            throw ValidationException::withMessages([
                'phone' => 'Impossible d’envoyer le code WhatsApp pour le moment. Réessayez.',
            ]);
        }

        $json = $response->json();
        $queued = is_array($json) && ($json['success'] ?? false) === true;

        if ($response->successful() && $queued) {
            return;
        }

        Log::error('Zapwize WhatsApp failed', [
            'chatid' => $chatId,
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 500),
        ]);

        throw ValidationException::withMessages([
            'phone' => 'Impossible d’envoyer le code WhatsApp pour le moment. Réessayez.',
        ]);
    }
}
