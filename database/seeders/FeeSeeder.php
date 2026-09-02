<?php

namespace Database\Seeders;

use App\Data\FeeModeEnum;
use App\Data\FeePartEnum;
use App\Data\TransactionTypeEnum;
use App\Models\Fee;
use Illuminate\Database\Seeder;

class FeeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->fees() as $fee) {
            Fee::query()->updateOrCreate(
                ['name' => $fee['name']],
                $fee,
            );
        }
    }

    /**
     * Frais actuellement configurés en admin.
     *
     * @return list<array<string, mixed>>
     */
    protected function fees(): array
    {
        return [
            [
                'name' => 'Transfert plateforme 1,5%',
                'transaction_type' => TransactionTypeEnum::NETWORK_TRANSFER,
                'part' => FeePartEnum::PLATFORM,
                'mode' => FeeModeEnum::PERCENTAGE,
                'value' => 1.5,
                'min_fee' => null,
                'max_fee' => null,
                'min_amount' => null,
                'max_amount' => null,
                'network_id' => null,
                'counterpart_network_id' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Ticket plateforme 200 F',
                'transaction_type' => TransactionTypeEnum::TICKET_PURCHASE,
                'part' => FeePartEnum::PLATFORM,
                'mode' => FeeModeEnum::FIXED,
                'value' => 200,
                'min_fee' => null,
                'max_fee' => null,
                'min_amount' => null,
                'max_amount' => null,
                'network_id' => null,
                'counterpart_network_id' => null,
                'is_active' => true,
            ],
        ];
    }
}
