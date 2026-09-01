<?php

namespace Database\Seeders;

use App\Data\TravelTypeEnum;
use App\Models\TravelCompany;
use Illuminate\Database\Seeder;

class TravelCompanySeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'name' => 'STAF',
                'phone' => '25300001',
                'station' => 'Gare de Ouagadougou Nord',
                'routes' => [
                    ['Ouagadougou', 'Bobo-Dioulasso', TravelTypeEnum::VIP, '7500', '07:00', '12:30', 12],
                    ['Ouagadougou', 'Koudougou', TravelTypeEnum::CLASSIC, '2500', '09:00', '11:30', 18],
                    ['Bobo-Dioulasso', 'Ouagadougou', TravelTypeEnum::VIP, '7500', '06:30', '12:00', 10],
                ],
            ],
            [
                'name' => 'TSR',
                'phone' => '25300002',
                'station' => 'Gare de Ouagadougou Centre',
                'routes' => [
                    ['Ouagadougou', 'Bobo-Dioulasso', TravelTypeEnum::CLASSIC, '5000', '08:30', '14:00', 23],
                    ['Ouagadougou', 'Koudougou', TravelTypeEnum::CLASSIC, '2500', '07:15', '09:45', 20],
                    ['Bobo-Dioulasso', 'Ouagadougou', TravelTypeEnum::CLASSIC, '5000', '15:00', '20:30', 16],
                ],
            ],
            [
                'name' => 'Rakieta',
                'phone' => '25300003',
                'station' => 'Gare de Ouagadougou Sud',
                'routes' => [
                    ['Ouagadougou', 'Bobo-Dioulasso', TravelTypeEnum::AC, '6000', '10:00', '16:00', 5],
                    ['Bobo-Dioulasso', 'Ouagadougou', TravelTypeEnum::AC, '6000', '11:00', '17:00', 8],
                ],
            ],
            [
                'name' => 'TCV',
                'phone' => '25300004',
                'station' => 'Gare de Ouagadougou Est',
                'routes' => [
                    ['Ouagadougou', 'Bobo-Dioulasso', TravelTypeEnum::CLASSIC, '4500', '14:00', '20:00', 30],
                    ['Ouagadougou', 'Koudougou', TravelTypeEnum::CLASSIC, '2200', '16:00', '18:20', 14],
                ],
            ],
        ];

        foreach ($catalog as $item) {
            $company = TravelCompany::query()->updateOrCreate(
                ['name' => $item['name']],
                [
                    'email' => strtolower($item['name']).'@travel.facilya.local',
                    'phone' => $item['phone'],
                    'address' => $item['station'],
                    'is_active' => true,
                ],
            );

            $station = $company->stations()->updateOrCreate(
                ['station_name' => $item['station']],
                [
                    'phone' => $item['phone'],
                    'address' => $item['station'],
                    'is_active' => true,
                ],
            );

            foreach ($item['routes'] as [$departure, $arrival, $type, $price, $depHour, $arrHour, $seats]) {
                $route = $company->routes()->updateOrCreate(
                    [
                        'departure' => $departure,
                        'arrival' => $arrival,
                        'travel_type' => $type,
                    ],
                    [
                        'price' => $price,
                        'is_active' => true,
                    ],
                );

                $route->trips()->updateOrCreate(
                    [
                        'travel_company_station_id' => $station->id,
                        'departure_hour' => $depHour.':00',
                    ],
                    [
                        'arrival_hour' => $arrHour.':00',
                        'available_seats' => $seats,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
