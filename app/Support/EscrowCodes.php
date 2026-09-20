<?php

namespace App\Support;

use App\Models\ParcelShipment;
use App\Models\Transaction;
use App\Models\TrustedPayment;

class EscrowCodes
{
    public static function from(mixed $record): ?TrustedPayment
    {
        if ($record instanceof TrustedPayment) {
            return $record;
        }

        if ($record instanceof ParcelShipment) {
            $record->loadMissing('trustedPayment');

            return $record->trustedPayment;
        }

        if ($record instanceof Transaction) {
            $record->loadMissing(['trustedPayment', 'parcelShipment.trustedPayment']);

            return $record->trustedPayment ?? $record->parcelShipment?->trustedPayment;
        }

        return null;
    }
}
