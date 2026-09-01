<?php

namespace App\Console\Commands;

use App\Models\RelayDevice;
use App\Support\Phone;
use Illuminate\Console\Command;

class RegisterRelayDevice extends Command
{
    protected $signature = 'relay:register
        {name : Nom de l’appareil TelRelayX}
        {--network=orange : Réseau principal (orange, moov, wave, telecel)}
        {--phone= : Numéro SIM de l’appareil}';

    protected $description = 'Enregistre un appareil TelRelayX et affiche le jeton Bearer';

    public function handle(): int
    {
        $phone = trim((string) $this->option('phone'));

        $device = RelayDevice::query()->create([
            'name' => $this->argument('name'),
            'network' => strtolower((string) $this->option('network')),
            'phone_number' => $phone === '' ? null : Phone::normalize($phone),
        ]);

        $token = $device->createToken('relay')->plainTextToken;

        $this->info('Appareil TelRelayX enregistré.');
        $this->line('Nom   : '.$device->name);
        $this->line('UUID  : '.$device->uuid);
        $this->line('Réseau: '.$device->network);
        $this->newLine();
        $this->warn('Jeton Bearer (à coller dans TelRelayX, il ne sera plus affiché) :');
        $this->line($token);

        return self::SUCCESS;
    }
}
