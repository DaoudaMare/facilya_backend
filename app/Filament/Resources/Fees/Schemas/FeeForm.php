<?php

namespace App\Filament\Resources\Fees\Schemas;

use App\Data\FeeModeEnum;
use App\Data\FeePartEnum;
use App\Data\TransactionTypeEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class FeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Règle')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Libellé')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('transaction_type')
                            ->label('Type d’opération')
                            ->options(TransactionTypeEnum::class)
                            ->required()
                            ->live(),
                        Select::make('part')
                            ->label('Part')
                            ->options(FeePartEnum::class)
                            ->required(),
                        Select::make('mode')
                            ->label('Mode')
                            ->options(FeeModeEnum::class)
                            ->required()
                            ->live(),
                        TextInput::make('value')
                            ->label('Valeur')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->helperText(fn (Get $get): string => $get('mode') === FeeModeEnum::PERCENTAGE->value
                                ? 'Pourcentage appliqué au prix du service (ticket ou montant transféré).'
                                : 'Montant fixe en F CFA, indépendant du prix du service.'),
                        TextInput::make('min_fee')
                            ->label('Frais min.')
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn (Get $get): bool => $get('mode') === FeeModeEnum::PERCENTAGE->value),
                        TextInput::make('max_fee')
                            ->label('Frais max.')
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn (Get $get): bool => $get('mode') === FeeModeEnum::PERCENTAGE->value),
                    ]),
                Section::make('Portée')
                    ->columns(2)
                    ->schema([
                        Select::make('network_id')
                            ->label('Réseau')
                            ->relationship('network', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Laisser vide pour une règle globale.'),
                        Select::make('counterpart_network_id')
                            ->label('Réseau destination')
                            ->relationship('counterpartNetwork', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => $get('transaction_type') === TransactionTypeEnum::NETWORK_TRANSFER->value)
                            ->helperText('Pour un corridor précis, ex. Orange → Wave.'),
                        TextInput::make('min_amount')
                            ->label('Montant min.')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('max_amount')
                            ->label('Montant max.')
                            ->numeric()
                            ->minValue(0),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
            ]);
    }
}
