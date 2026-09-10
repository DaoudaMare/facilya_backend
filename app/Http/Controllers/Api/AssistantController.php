<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AssistantController extends Controller
{
    public function __construct(
        protected AssistantService $assistant,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'in:user,model'],
            'history.*.text' => ['required_with:history', 'string', 'max:4000'],
            'departure' => ['nullable', 'string', 'max:120'],
            'arrival' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $started = microtime(true);
        $historyCount = count($data['history'] ?? []);

        Log::info('assistant.chat.request', [
            'user_id' => $user?->id,
            'message_preview' => mb_substr(trim($data['message']), 0, 200),
            'message_length' => mb_strlen($data['message']),
            'history_count' => $historyCount,
            'departure' => $data['departure'] ?? null,
            'arrival' => $data['arrival'] ?? null,
        ]);

        try {
            $result = $this->assistant->ask(
                $data['message'],
                $user,
                $data['history'] ?? [],
                $data['departure'] ?? null,
                $data['arrival'] ?? null,
            );
        } catch (RuntimeException $e) {
            Log::warning('assistant.chat.failed', [
                'user_id' => $user?->id,
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        } catch (Throwable $e) {
            Log::error('assistant.chat.exception', [
                'user_id' => $user?->id,
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return response()->json([
                'message' => 'Une erreur inattendue est survenue côté assistance.',
            ], 500);
        }

        Log::info('assistant.chat.success', [
            'user_id' => $user?->id,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            'model' => $result['model'] ?? null,
            'reply_preview' => mb_substr((string) ($result['reply'] ?? ''), 0, 200),
            'reply_length' => mb_strlen((string) ($result['reply'] ?? '')),
        ]);

        return response()->json([
            'data' => $result,
        ]);
    }
}
