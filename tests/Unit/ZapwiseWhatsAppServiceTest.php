<?php

namespace Tests\Unit;

use App\Services\ZapwiseWhatsAppService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZapwiseWhatsAppServiceTest extends TestCase
{
    public function test_sends_the_official_zapwize_curl_payload(): void
    {
        Http::fake([
            'https://api.zapwize.com/v1/whatsapp/message' => Http::response(['success' => true], 200),
        ]);

        app(ZapwiseWhatsAppService::class)->sendText('07684843', 'Hello from Zapwize!');

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return $request->method() === 'POST'
                && $request->url() === 'https://api.zapwize.com/v1/whatsapp/message'
                && $request->hasHeader('Authorization', 'Bearer testing')
                && str_starts_with((string) $request->header('Content-Type')[0], 'application/json')
                && $payload === [
                    'chatid' => '22607684843',
                    'content' => [
                        'text' => 'Hello from Zapwize!',
                    ],
                ];
        });
    }
}
