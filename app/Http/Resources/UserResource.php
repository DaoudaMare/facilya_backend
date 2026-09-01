<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->publicEmail(),
            'phone' => $this->phone,
            'phone_formatted' => $this->phone ? Phone::format($this->phone) : null,
            'needs_pin' => ! $this->hasPin(),
        ];
    }

    protected function publicEmail(): ?string
    {
        $email = (string) $this->email;

        if ($email === '' || str_ends_with($email, '@users.facilya.local')) {
            return null;
        }

        return $email;
    }
}
