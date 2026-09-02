<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $auth,
    ) {}

    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:sms,email,whatsapp'],
            'phone' => ['required_if:channel,sms,whatsapp', 'nullable', 'string', 'max:32'],
            'email' => ['required_if:channel,email', 'nullable', 'email', 'max:120'],
        ]);

        return response()->json([
            'data' => $this->auth->requestOtp(
                $data['channel'],
                $data['phone'] ?? null,
                $data['email'] ?? null,
            ),
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:sms,email,whatsapp'],
            'phone' => ['required_if:channel,sms,whatsapp', 'nullable', 'string', 'max:32'],
            'email' => ['required_if:channel,email', 'nullable', 'email', 'max:120'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $result = $this->auth->verifyOtp(
            $data['channel'],
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['code'],
        );

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'needs_pin' => $result['needs_pin'],
                'user' => UserResource::make($result['user']),
            ],
        ]);
    }

    public function setPin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ]);

        $user = $this->auth->setPin($request->user(), $data['pin']);

        return response()->json([
            'data' => [
                'user' => UserResource::make($user),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => UserResource::make($request->user()),
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $user = $this->auth->updateProfile($request->user(), $data);

        return response()->json([
            'data' => UserResource::make($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return response()->json(['data' => ['ok' => true]]);
    }
}
