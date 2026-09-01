<?php

namespace App\Http\Resources;

use App\Models\TransferNetwork;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransferNetwork
 */
class TransferNetworkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $code = $this->code?->value ?? (string) $this->code;

        return [
            'id' => $this->id,
            'code' => $code,
            'name' => $this->name,
            'can_send' => $this->can_send,
            'can_receive' => $this->can_receive,
            'receive_phone' => $this->receive_phone,
            'payment_ussd' => $this->payment_ussd,
        ];
    }
}
