<?php

namespace App\Filament\Livewire;

use App\Models\TrustedPayment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class RevealEscrowUnlockCode extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public int $trustedPaymentId;

    public bool $revealed = false;

    public static function canViewUnlockCodes(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User && $user->hasPermission('trusted_payments.view_unlock_codes');
    }

    public function revealAction(): Action
    {
        return Action::make('reveal')
            ->label('Afficher le code de déblocage')
            ->icon('heroicon-o-eye')
            ->color('warning')
            ->visible(fn (): bool => ! $this->revealed && self::canViewUnlockCodes())
            ->modalHeading('Confirmer votre identité')
            ->modalDescription('Le code de déblocage des fonds n’est visible qu’après validation de votre mot de passe.')
            ->modalSubmitActionLabel('Afficher')
            ->form([
                TextInput::make('password')
                    ->label('Mot de passe')
                    ->password()
                    ->revealable()
                    ->required()
                    ->currentPassword()
                    ->autocomplete('current-password'),
            ])
            ->action(function (): void {
                if (! self::canViewUnlockCodes()) {
                    throw ValidationException::withMessages([
                        'password' => 'Vous n’avez pas la permission de voir ce code.',
                    ]);
                }

                $this->revealed = true;

                Notification::make()
                    ->title('Code de déblocage visible')
                    ->body('Masquez-le dès que vous n’en avez plus besoin.')
                    ->warning()
                    ->send();
            });
    }

    public function hideAction(): Action
    {
        return Action::make('hide')
            ->label('Masquer')
            ->icon('heroicon-o-eye-slash')
            ->color('gray')
            ->visible(fn (): bool => $this->revealed)
            ->action(function (): void {
                $this->revealed = false;
            });
    }

    public function payment(): ?TrustedPayment
    {
        return TrustedPayment::query()->find($this->trustedPaymentId);
    }

    public function render(): View
    {
        $payment = $this->payment();

        return view('livewire.reveal-escrow-unlock-code', [
            'publicId' => $payment?->public_id,
            'unlockCode' => $this->revealed ? $payment?->validation_code : null,
            'canReveal' => self::canViewUnlockCodes(),
        ]);
    }
}
