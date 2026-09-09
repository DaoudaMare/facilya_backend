<?php

namespace Database\Seeders;

use App\Models\ReferralSetting;
use Illuminate\Database\Seeder;

class ReferralSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = ReferralSetting::current();
        $settings->fill([
            'is_active' => true,
            'commission_percent' => 0.5,
            'max_rewarded_transactions' => 10,
        ])->save();
    }
}
