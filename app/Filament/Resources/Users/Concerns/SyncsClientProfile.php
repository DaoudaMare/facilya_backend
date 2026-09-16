<?php

namespace App\Filament\Resources\Users\Concerns;

use App\Support\Phone;

trait SyncsClientProfile
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function syncClientProfileData(array $data): array
    {
        $first = trim((string) ($data['first_name'] ?? ''));
        $last = trim((string) ($data['last_name'] ?? ''));
        $data['first_name'] = $first !== '' ? $first : null;
        $data['last_name'] = $last !== '' ? $last : null;
        $data['name'] = trim($first.' '.$last);
        if ($data['name'] === '') {
            $data['name'] = 'Client Facilya';
        }

        $data['phone'] = $this->normalizedPhone($data['phone'] ?? null);
        $data['phone_secondary'] = $this->normalizedPhone($data['phone_secondary'] ?? null);

        return $data;
    }

    protected function normalizedPhone(mixed $value): ?string
    {
        $raw = trim((string) $value);

        return $raw === '' ? null : Phone::normalize($raw);
    }
}
