<?php

namespace App\Filament\Resources\ParcelPricingSettings\Schemas;

use App\Data\ParcelFeeModeEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ParcelPricingSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transport agence')
                    ->description('Prix payé / facturé pour le trajet bus. Le % s’applique au prix ticket de la route.')
                    ->columns(2)
                    ->schema([
                        Select::make('agency_mode')
                            ->label('Mode')
                            ->options(ParcelFeeModeEnum::class)
                            ->required()
                            ->live(),
                        TextInput::make('agency_value')
                            ->label(fn (Get $get): string => $get('agency_mode') === ParcelFeeModeEnum::Percentage->value
                                ? 'Pourcentage (%)'
                                : 'Montant fixe (F CFA)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ]),
                Section::make('Marge Facilya')
                    ->description('Le % s’applique au montant agence.')
                    ->columns(2)
                    ->schema([
                        Select::make('margin_mode')
                            ->label('Mode')
                            ->options(ParcelFeeModeEnum::class)
                            ->required()
                            ->live(),
                        TextInput::make('margin_value')
                            ->label(fn (Get $get): string => $get('margin_mode') === ParcelFeeModeEnum::Percentage->value
                                ? 'Pourcentage (%)'
                                : 'Montant fixe (F CFA)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                    ]),
                Section::make('Collecte à domicile (pickup)')
                    ->description('Base fixe ou % (sur agence + marge) + frais auto = km × prix/km.')
                    ->columns(2)
                    ->schema([
                        Select::make('pickup_mode')
                            ->label('Mode base')
                            ->options(ParcelFeeModeEnum::class)
                            ->required()
                            ->live(),
                        TextInput::make('pickup_value')
                            ->label(fn (Get $get): string => $get('pickup_mode') === ParcelFeeModeEnum::Percentage->value
                                ? 'Base (%)'
                                : 'Base fixe (F CFA)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('pickup_per_km')
                            ->label('Prix au km (F CFA)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('Frais auto collecte = distance_km × ce tarif. Mettre 0 pour désactiver.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Livraison à domicile (drop-off)')
                    ->description('Uniquement si le client choisit la livraison à la porte. Même logique que le pickup.')
                    ->columns(2)
                    ->schema([
                        Select::make('delivery_mode')
                            ->label('Mode base')
                            ->options(ParcelFeeModeEnum::class)
                            ->required()
                            ->live(),
                        TextInput::make('delivery_value')
                            ->label(fn (Get $get): string => $get('delivery_mode') === ParcelFeeModeEnum::Percentage->value
                                ? 'Base (%)'
                                : 'Base fixe (F CFA)')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('delivery_per_km')
                            ->label('Prix au km (F CFA)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('Frais auto livraison = distance_km × ce tarif. Mettre 0 pour désactiver.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Assurance / valeur déclarée')
                    ->description('Pourcentage appliqué à la valeur déclarée du colis (0 = désactivé).')
                    ->schema([
                        TextInput::make('value_fee_percent')
                            ->label('Pourcentage de la valeur (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required()
                            ->helperText('Ex. 1 = 1 % de la valeur déclarée ajouté au tarif.'),
                    ]),
            ]);
    }
}
