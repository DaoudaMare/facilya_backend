<?php

namespace Database\Seeders;

use App\Data\TransferNetworkEnum;
use App\Models\TransferNetwork;
use Illuminate\Database\Seeder;

class TransferNetworkSeeder extends Seeder
{
    public function run(): void
    {
        foreach (TransferNetworkEnum::cases() as $network) {
            TransferNetwork::query()->updateOrCreate(
                ['code' => $network->value],
                [
                    'name' => $network->label(),
                    'can_send' => true,
                    'can_receive' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
