<?php

namespace App\Models;

use App\Data\TransferNetworkEnum;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransferNetwork extends Model
{
    protected $fillable = [
        'code',
        'name',
        'can_send',
        'can_receive',
        'receive_phone',
        'payment_ussd',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'code' => TransferNetworkEnum::class,
            'can_send' => 'boolean',
            'can_receive' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function sourceTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'source_network_id');
    }

    public function destinationTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'destination_network_id');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'payment_network_id');
    }

    public function fees(): HasMany
    {
        return $this->hasMany(Fee::class, 'network_id');
    }

    public function counterpartFees(): HasMany
    {
        return $this->hasMany(Fee::class, 'counterpart_network_id');
    }

    public function relayCode(): string
    {
        $code = $this->code instanceof TransferNetworkEnum
            ? $this->code->value
            : (string) $this->code;

        return strtolower($code);
    }

    public function paymentUssdCode(string|int|float $amount): ?string
    {
        $template = trim((string) $this->payment_ussd);

        if ($template === '') {
            return null;
        }

        $phone = Phone::normalize((string) $this->receive_phone);
        $payAmount = (string) (int) round((float) $amount);

        return strtr($template, [
            '{numero}' => $phone,
            '{phone}' => $phone,
            '{montant}' => $payAmount,
            '{amount}' => $payAmount,
        ]);
    }
}
