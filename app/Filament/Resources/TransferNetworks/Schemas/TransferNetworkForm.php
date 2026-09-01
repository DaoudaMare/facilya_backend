<?php

namespace App\Filament\Resources\TransferNetworks\Schemas;

use App\Data\TransferNetworkEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TransferNetworkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('code')
                            ->label('Code')
                            ->options(TransferNetworkEnum::class)
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $enum = TransferNetworkEnum::tryFrom((string) $state);

                                if ($enum) {
                                    $set('name', $enum->label());
                                }
                            }),
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('can_send')
                            ->label('Peut envoyer')
                            ->default(true),
                        Toggle::make('can_receive')
                            ->label('Peut recevoir')
                            ->default(true),
                        TextInput::make('receive_phone')
                            ->label('Numéro de réception des paiements')
                            ->helperText('Numéro Mobile Money sur lequel les clients envoient le paiement pour ce réseau.')
                            ->tel()
                            ->maxLength(32)
                            ->placeholder('70 00 00 00')
                            ->columnSpanFull(),
                        TextInput::make('payment_ussd')
                            ->label('Code USSD de paiement')
                            ->helperText('Variables : {numero} (réception) et {montant}. Exemple : *144*1*1*{numero}*{montant}#')
                            ->maxLength(120)
                            ->placeholder('*144*1*1*{numero}*{montant}#')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Actif')
                            ->default(true),
                    ]),
            ]);
    }
}
