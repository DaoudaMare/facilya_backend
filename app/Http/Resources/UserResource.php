<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Models\UserAddress;
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
        $this->resource->loadMissing('addresses');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->publicEmail(),
            'phone' => $this->phone,
            'phone_formatted' => $this->phone ? Phone::format($this->phone) : null,
            'phone_secondary' => $this->phone_secondary,
            'phone_secondary_formatted' => $this->phone_secondary ? Phone::format($this->phone_secondary) : null,
            'cnib_number' => $this->cnib_number,
            'cnib_photo_url' => $this->cnibPhotoUrl(),
            'needs_pin' => ! $this->hasPin(),
            'profile_complete' => $this->isProfileComplete(),
            'profile_missing' => $this->profileMissing(),
            'is_email_account' => $this->isEmailAccount(),
            'addresses' => $this->addresses->map(function (UserAddress $address) {
                return [
                    'id' => $address->id,
                    'name' => $address->name,
                    'maps_url' => $address->maps_url,
                ];
            })->values(),
            'referral_code' => $this->referral_code,
            'reward_balance' => number_format((float) ($this->reward_balance ?? 0), 0, '.', ''),
            'referred_by_user_id' => $this->referred_by_user_id,
        ];
    }
}
