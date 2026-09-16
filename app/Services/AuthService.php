<?php

namespace App\Services;

use App\Channels\SmsChannel;
use App\Channels\WhatsAppChannel;
use App\Models\Role;
use App\Models\User;
use App\Notifications\OtpCodeNotification;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\GoogleMapsLink;
use App\Support\Phone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function __construct(
        protected UserRepositoryInterface $users,
        protected ReferralService $referrals,
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
     * @return array{token: string, user: User, needs_pin: bool, is_new: bool}
     */
    public function verifyOtp(
        string $channel,
        ?string $rawPhone,
        ?string $rawEmail,
        string $code,
        ?string $referralCode = null,
    ): array {
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

        [$user, $created] = $this->findOrCreateWithFlag($channel, $destination);

        if (! $user->role_id) {
            $user = $this->users->update($user, [
                'role_id' => $this->defaultClientRoleId(),
            ]);
        }

        $user = $this->referrals->ensureReferralCode($user);

        if ($created) {
            $this->referrals->attachOnSignup($user, $referralCode);
            $user = $user->fresh() ?? $user;
        }

        $user->tokens()->where('name', 'mobile')->delete();

        return [
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => $user,
            'needs_pin' => ! $user->hasPin(),
            'is_new' => $created,
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
     * @param  array<string, mixed>  $attributes
     */
    public function updateProfile(User $user, array $attributes): User
    {
        $payload = [];

        $first = array_key_exists('first_name', $attributes)
            ? trim((string) $attributes['first_name'])
            : $user->first_name;
        $last = array_key_exists('last_name', $attributes)
            ? trim((string) $attributes['last_name'])
            : $user->last_name;

        if (array_key_exists('name', $attributes) && filled($attributes['name']) && ! array_key_exists('first_name', $attributes)) {
            $parts = preg_split('/\s+/', trim((string) $attributes['name']), 2) ?: [];
            $first = $parts[0] ?? $first;
            $last = $parts[1] ?? $last;
        }

        if (array_key_exists('first_name', $attributes) || array_key_exists('last_name', $attributes) || array_key_exists('name', $attributes)) {
            $payload['first_name'] = filled($first) ? $first : null;
            $payload['last_name'] = filled($last) ? $last : null;
            $payload['name'] = trim(implode(' ', array_filter([$payload['first_name'], $payload['last_name']])));
            if ($payload['name'] === '') {
                $payload['name'] = 'Client Facilya';
            }
        }

        if (array_key_exists('phone', $attributes)) {
            $payload['phone'] = $this->uniquePhone($user, $attributes['phone'], 'phone');
        }

        if (array_key_exists('phone_secondary', $attributes)) {
            $secondary = $this->uniquePhone($user, $attributes['phone_secondary'], 'phone_secondary');
            $primary = $payload['phone'] ?? $user->phone;
            if ($secondary !== null && $primary !== null && Phone::matches($secondary, (string) $primary)) {
                throw ValidationException::withMessages([
                    'phone_secondary' => 'Le second numéro doit être différent du premier.',
                ]);
            }
            if ($secondary !== null && blank($primary)) {
                throw ValidationException::withMessages([
                    'phone_secondary' => 'Renseignez d’abord un numéro principal.',
                ]);
            }
            $payload['phone_secondary'] = $secondary;
        }

        if (array_key_exists('cnib_number', $attributes)) {
            $cnib = trim((string) $attributes['cnib_number']);
            $payload['cnib_number'] = $cnib !== '' ? $cnib : null;
        }

        if (($attributes['cnib_photo'] ?? null) instanceof UploadedFile) {
            $path = $attributes['cnib_photo']->store('users/cnib', 'public');
            if (! is_string($path) || $path === '') {
                throw ValidationException::withMessages([
                    'cnib_photo' => 'Impossible d’enregistrer la photo CNIB.',
                ]);
            }
            if (filled($user->cnib_photo) && $user->cnib_photo !== $path) {
                Storage::disk('public')->delete($user->cnib_photo);
            }
            $payload['cnib_photo'] = $path;
        }

        $user = $payload === [] ? $user : $this->users->update($user, $payload);

        if (array_key_exists('addresses', $attributes)) {
            $this->syncAddresses($user, is_array($attributes['addresses']) ? $attributes['addresses'] : []);
        }

        return $user->fresh(['addresses']) ?? $user;
    }

    protected function uniquePhone(User $user, mixed $raw, string $field): ?string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        $normalized = Phone::normalize($value);
        if (! Phone::isValid($normalized)) {
            throw ValidationException::withMessages([
                $field => 'Indiquez un numéro burkinabè valide.',
            ]);
        }

        $taken = User::query()
            ->where('id', '!=', $user->id)
            ->where(function ($query) use ($normalized) {
                $query->where('phone', $normalized)
                    ->orWhere('phone_secondary', $normalized);
            })
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                $field => 'Ce numéro est déjà utilisé.',
            ]);
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $addresses
     */
    protected function syncAddresses(User $user, array $addresses): void
    {
        $keepIds = [];

        foreach ($addresses as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $maps = GoogleMapsLink::normalize((string) ($row['maps_url'] ?? ''));

            if ($name === '' && $maps === '') {
                continue;
            }

            if ($name === '') {
                throw ValidationException::withMessages([
                    "addresses.$index.name" => 'Indiquez le nom de l’adresse (ex. Domicile, Boutique).',
                ]);
            }

            if ($maps === '') {
                throw ValidationException::withMessages([
                    "addresses.$index.maps_url" => 'Le lien Google Maps est obligatoire.',
                ]);
            }

            if (! GoogleMapsLink::isValid($maps)) {
                throw ValidationException::withMessages([
                    "addresses.$index.maps_url" => GoogleMapsLink::validationMessage(),
                ]);
            }

            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $address = $id > 0
                ? $user->addresses()->whereKey($id)->first()
                : null;

            if (! $address) {
                $address = $user->addresses()->make();
            }

            $address->fill([
                'name' => $name,
                'maps_url' => $maps,
            ])->save();

            $keepIds[] = $address->id;
        }

        $user->addresses()
            ->when($keepIds !== [], fn ($query) => $query->whereNotIn('id', $keepIds), fn ($query) => $query)
            ->delete();
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

            if ($channel === 'whatsapp') {
                Notification::route(WhatsAppChannel::class, $destination)->notifyNow($notification);

                return;
            }

            Notification::route(SmsChannel::class, $destination)->notifyNow($notification);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                $channel === 'email' ? 'email' : 'phone' => match ($channel) {
                    'email' => 'Impossible d’envoyer l’e-mail pour le moment. Réessayez.',
                    'whatsapp' => 'Impossible d’envoyer le code WhatsApp pour le moment. Réessayez.',
                    default => 'Impossible d’envoyer le SMS pour le moment. Réessayez.',
                },
            ]);
        }
    }

    /**
     * @return array{0: User, 1: bool}
     */
    protected function findOrCreateWithFlag(string $channel, string $destination): array
    {
        if ($channel === 'email') {
            $user = $this->users->findByEmail($destination);

            if ($user) {
                return [$user, false];
            }

            $local = Str::before($destination, '@');

            return [$this->users->create([
                'name' => filled($local) ? Str::title(str_replace(['.', '_', '-'], ' ', $local)) : 'Client Facilya',
                'email' => $destination,
                'password' => Str::password(32),
                'email_verified_at' => now(),
                'role_id' => $this->defaultClientRoleId(),
            ]), true];
        }

        $user = $this->users->findByPhone($destination);

        if ($user) {
            return [$user, false];
        }

        return [$this->users->create([
            'name' => 'Client Facilya',
            'email' => $destination.'@users.facilya.local',
            'phone' => $destination,
            'password' => Str::password(32),
            'email_verified_at' => now(),
            'role_id' => $this->defaultClientRoleId(),
        ]), true];
    }

    protected function defaultClientRoleId(): ?int
    {
        $id = Role::query()->where('slug', 'client')->value('id');

        return $id ? (int) $id : null;
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

        if (! in_array($channel, ['sms', 'email', 'whatsapp'], true)) {
            throw ValidationException::withMessages([
                'channel' => 'Choisissez l’envoi par WhatsApp, SMS ou e-mail.',
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
