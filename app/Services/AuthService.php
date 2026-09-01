<?php

namespace App\Services;

use App\Channels\SmsChannel;
use App\Models\User;
use App\Notifications\OtpCodeNotification;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\Phone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function __construct(
        protected UserRepositoryInterface $users,
    ) {}

    /**
     * @return array{channel: string, destination: string, expires_in: int}
     */
    public function requestOtp(string $channel, ?string $rawPhone, ?string $rawEmail): array
    {
        $channel = $this->validatedChannel($channel);
        $destination = $this->destinationFor($channel, $rawPhone, $rawEmail);
        $code = (string) random_int(100000, 999999);

        Cache::put($this->otpKey($channel, $destination), $code, now()->addMinutes(10));
        Cache::forget($this->otpAttemptsKey($channel, $destination));

        $this->dispatchOtp($channel, $destination, $code);

        return [
            'channel' => $channel,
            'destination' => $this->maskedDestination($channel, $destination),
            'expires_in' => 600,
        ];
    }

    /**
     * @return array{token: string, user: User, needs_pin: bool}
     */
    public function verifyOtp(string $channel, ?string $rawPhone, ?string $rawEmail, string $code): array
    {
        $channel = $this->validatedChannel($channel);
        $destination = $this->destinationFor($channel, $rawPhone, $rawEmail);
        $expected = Cache::get($this->otpKey($channel, $destination));

        if (! is_string($expected)) {
            throw ValidationException::withMessages([
                'code' => 'Le code OTP a expiré. Demandez-en un nouveau.',
            ]);
        }

        $attempts = (int) Cache::get($this->otpAttemptsKey($channel, $destination), 0);
        if ($attempts >= 5) {
            Cache::forget($this->otpKey($channel, $destination));
            throw ValidationException::withMessages([
                'code' => 'Trop de tentatives. Demandez un nouveau code.',
            ]);
        }

        if (! hash_equals($expected, $code)) {
            Cache::put($this->otpAttemptsKey($channel, $destination), $attempts + 1, now()->addMinutes(10));
            throw ValidationException::withMessages([
                'code' => 'Code OTP incorrect.',
            ]);
        }

        Cache::forget($this->otpKey($channel, $destination));
        Cache::forget($this->otpAttemptsKey($channel, $destination));

        $user = $this->findOrCreate($channel, $destination);
        $user->tokens()->where('name', 'mobile')->delete();

        return [
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => $user,
            'needs_pin' => ! $user->hasPin(),
        ];
    }

    public function setPin(User $user, string $pin): User
    {
        $this->assertPinFormat($pin);

        return $this->users->update($user, ['pin' => $pin]);
    }

    public function assertPin(User $user, string $pin): void
    {
        if (! $user->hasPin()) {
            throw ValidationException::withMessages([
                'pin' => 'Aucun code PIN n’est défini sur ce compte.',
            ]);
        }

        $this->assertPinFormat($pin);

        if (! Hash::check($pin, (string) $user->pin)) {
            throw ValidationException::withMessages([
                'pin' => 'Code PIN incorrect.',
            ]);
        }
    }

    /**
     * @param  array{name?: string}  $attributes
     */
    public function updateProfile(User $user, array $attributes): User
    {
        $payload = [];

        if (array_key_exists('name', $attributes) && filled($attributes['name'])) {
            $payload['name'] = trim((string) $attributes['name']);
        }

        return $payload === [] ? $user : $this->users->update($user, $payload);
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    protected function dispatchOtp(string $channel, string $destination, string $code): void
    {
        $notification = new OtpCodeNotification($code, $channel);

        try {
            if ($channel === 'email') {
                Notification::route('mail', $destination)->notifyNow($notification);

                return;
            }

            Notification::route(SmsChannel::class, $destination)->notifyNow($notification);
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                $channel === 'email' ? 'email' : 'phone' => $channel === 'email'
                    ? 'Impossible d’envoyer l’e-mail pour le moment. Réessayez.'
                    : 'Impossible d’envoyer le SMS pour le moment. Réessayez.',
            ]);
        }
    }

    protected function findOrCreate(string $channel, string $destination): User
    {
        if ($channel === 'email') {
            $user = $this->users->findByEmail($destination);

            if ($user) {
                return $user;
            }

            $local = Str::before($destination, '@');

            return $this->users->create([
                'name' => filled($local) ? Str::title(str_replace(['.', '_', '-'], ' ', $local)) : 'Client Facilya',
                'email' => $destination,
                'password' => Str::password(32),
                'email_verified_at' => now(),
            ]);
        }

        $user = $this->users->findByPhone($destination);

        if ($user) {
            return $user;
        }

        return $this->users->create([
            'name' => 'Client Facilya',
            'email' => $destination.'@users.facilya.local',
            'phone' => $destination,
            'password' => Str::password(32),
            'email_verified_at' => now(),
        ]);
    }

    protected function destinationFor(string $channel, ?string $rawPhone, ?string $rawEmail): string
    {
        if ($channel === 'email') {
            $email = mb_strtolower(trim((string) $rawEmail));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'email' => 'Indiquez une adresse e-mail valide.',
                ]);
            }

            return $email;
        }

        $phone = Phone::normalize((string) $rawPhone);

        if (! Phone::isValid($phone)) {
            throw ValidationException::withMessages([
                'phone' => 'Indiquez un numéro burkinabè (8 à 10 chiffres).',
            ]);
        }

        return $phone;
    }

    protected function validatedChannel(string $channel): string
    {
        $channel = strtolower(trim($channel));

        if (! in_array($channel, ['sms', 'email'], true)) {
            throw ValidationException::withMessages([
                'channel' => 'Choisissez l’envoi par SMS ou par e-mail.',
            ]);
        }

        return $channel;
    }

    protected function maskedDestination(string $channel, string $destination): string
    {
        if ($channel === 'email') {
            [$local, $domain] = array_pad(explode('@', $destination, 2), 2, '');
            $visible = Str::substr($local, 0, 1);

            return $visible.'***@'.$domain;
        }

        $digits = Phone::normalize($destination);

        if (strlen($digits) < 4) {
            return $digits;
        }

        return str_repeat('•', max(0, strlen($digits) - 4)).substr($digits, -4);
    }

    protected function assertPinFormat(string $pin): void
    {
        if (! preg_match('/^\d{4}$/', $pin)) {
            throw ValidationException::withMessages([
                'pin' => 'Le code PIN doit contenir 4 chiffres.',
            ]);
        }
    }

    protected function otpKey(string $channel, string $destination): string
    {
        return "auth.otp.{$channel}.{$destination}";
    }

    protected function otpAttemptsKey(string $channel, string $destination): string
    {
        return "auth.otp.attempts.{$channel}.{$destination}";
    }
}
