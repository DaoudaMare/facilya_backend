<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SupportSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'parametres/support';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasAnyPermission('settings.manage');
    }

    public function mount(): void
    {
        $this->redirect(Configuration::tabUrl('support'), navigate: false);
    }
}
