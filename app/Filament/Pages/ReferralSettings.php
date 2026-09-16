<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ReferralSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'parametres/parrainage';

    public static function canAccess(): bool
    {
        return Configuration::canAccess();
    }

    public function mount(): void
    {
        $this->redirect(Configuration::tabUrl('parrainage'), navigate: false);
    }
}
