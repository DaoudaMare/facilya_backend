<?php

namespace Database\Seeders;

use App\Models\TransferNetwork;
use Illuminate\Database\Seeder;

class TransferNetworkSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->networks() as $network) {
            TransferNetwork::query()->updateOrCreate(
                ['code' => $network['code']],
                $network,
            );
        }
    }

    /**
     * Paramètres Paiements Mobile Money actuels (numéro + USSD de réception).
     *
     * @return list<array<string, mixed>>
     */
    protected function networks(): array
    {
        return [
            [
                'code' => 'MOOV',
                'name' => 'Moov Money',
                'can_send' => true,
                'can_receive' => true,
                'receive_phone' => '62765701',
                'payment_ussd' => '*555*2*1*{numero}*{montant}#',
                'description' => null,
                'is_active' => true,
            ],
            [
                'code' => 'ORANGE',
                'name' => 'Orange Money',
                'can_send' => true,
                'can_receive' => true,
                'receive_phone' => '07684843',
                'payment_ussd' => '*144*2*1*{numero}*{montant}#',
                'description' => null,
                'is_active' => true,
            ],
            [
                'code' => 'WAVE',
                'name' => 'Wave',
                'can_send' => true,
                'can_receive' => true,
                'receive_phone' => '07684843',
                'payment_ussd' => '*304*2*1*{numero}*{montant}#',
                'description' => null,
                'is_active' => true,
            ],
            [
                'code' => 'TELECEL',
                'name' => 'Telecel Money',
                'can_send' => true,
                'can_receive' => true,
                'receive_phone' => '79665808',
                'payment_ussd' => '*808*2*1*{numero}*{montant}#',
                'description' => null,
                'is_active' => true,
            ],
        ];
    }
}
