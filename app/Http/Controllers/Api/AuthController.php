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
            'referral_code' => ['nullable', 'string', 'max:16'],
        ]);

        $result = $this->auth->verifyOtp(
            $data['channel'],
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['code'],
            $data['referral_code'] ?? null,
        );

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'needs_pin' => $result['needs_pin'],
                'is_new' => $result['is_new'],
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
        $user = app(\App\Services\ReferralService::class)->ensureReferralCode($request->user());

        return response()->json([
            'data' => UserResource::make($user),
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'phone_secondary' => ['sometimes', 'nullable', 'string', 'max:32'],
            'cnib_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'cnib_photo' => ['sometimes', 'nullable', 'image', 'max:5120'],
            'addresses' => ['sometimes', 'array', 'max:8'],
            'addresses.*.id' => ['nullable', 'integer'],
            'addresses.*.name' => ['nullable', 'string', 'max:80'],
            'addresses.*.maps_url' => ['nullable', 'string', 'max:2048'],
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
