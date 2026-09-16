<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PaymentReceptionSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'parametres/numeros-reception';

    public static function canAccess(): bool
    {
        return Configuration::canAccess();
    }

    public function mount(): void
    {
        $this->redirect(Configuration::tabUrl('mobile-money'), navigate: false);
    }
}
