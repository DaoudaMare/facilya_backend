<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        try {
            $result = $this->assistant->ask(
                $data['message'],
                $request->user(),
                $data['history'] ?? [],
                $data['departure'] ?? null,
                $data['arrival'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 502);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Une erreur inattendue est survenue côté assistance.',
            ], 500);
        }

        return response()->json([
            'data' => $result,
        ]);
    }
}
