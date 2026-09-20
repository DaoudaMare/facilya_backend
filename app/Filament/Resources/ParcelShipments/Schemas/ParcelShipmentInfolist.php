<?php

namespace App\Filament\Resources\ParcelShipments\Schemas;

use App\Filament\Support\EscrowCodesSection;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ParcelShipmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                EscrowCodesSection::make(),
                Section::make('Envoi')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('reference')->label('Référence'),
                        TextEntry::make('status')->label('Statut de suivi')->badge(),
                        TextEntry::make('scope')->label('Type')->badge(),
                        TextEntry::make('delivery_mode')->label('Mode de livraison')->badge(),
                        TextEntry::make('travelCompany.name')->label('Compagnie'),
                        TextEntry::make('route.departure')->label('Départ'),
                        TextEntry::make('route.arrival')->label('Arrivée'),
                        TextEntry::make('parcel_description')->label('Description')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('estimated_weight_kg')->label('Poids estimé (kg)')->placeholder('—'),
                        ImageEntry::make('parcel_photo')
                            ->label('Photo du colis')
                            ->disk('public')
                            ->columnSpanFull(),
                    ]),
                Section::make('Expéditeur')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('sender_name')->label('Nom complet'),
                        TextEntry::make('sender_phone')->label('Téléphone'),
                        TextEntry::make('sender_cnib_number')->label('N° CNIB'),
                        TextEntry::make('pickup_code')->label('Code collecte')->copyable(),
                        TextEntry::make('trustedPayment.reference')
                            ->label('Paiement confiant')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('pickup_address')->label('Lien Maps collecte')->url(fn ($state) => filled($state) ? (string) $state : null)->openUrlInNewTab()->columnSpanFull(),
                        TextEntry::make('pickup_district')->label('Quartier')->placeholder('—'),
                        ImageEntry::make('sender_cnib_photo')
                            ->label('Photo CNIB expéditeur')
                            ->disk('public')
                            ->columnSpanFull(),
                    ]),
                Section::make('Destinataire')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('recipient_name')->label('Nom complet'),
                        TextEntry::make('recipient_phone')->label('Téléphone'),
                        TextEntry::make('recipient_cnib_number')->label('N° CNIB'),
                        TextEntry::make('delivery_code')->label('Code livraison')->placeholder('—')->copyable(),
                        TextEntry::make('station_pickup_code')->label('Code retrait gare')->placeholder('—')->copyable(),
                        TextEntry::make('recipient_address')->label('Lien Maps livraison')->placeholder('—')->url(fn ($state) => filled($state) ? (string) $state : null)->openUrlInNewTab()->columnSpanFull(),
                        TextEntry::make('recipient_district')->label('Quartier')->placeholder('—'),
                        ImageEntry::make('recipient_cnib_photo')
                            ->label('Photo CNIB destinataire')
                            ->disk('public')
                            ->columnSpanFull(),
                    ]),
                Section::make('Tarification')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('agency_fee')->label('Agence')->numeric()->suffix(' F'),
                        TextEntry::make('margin_amount')->label('Marge')->numeric()->suffix(' F'),
                        TextEntry::make('base_amount')->label('Transport (agence+marge)')->numeric()->suffix(' F'),
                        TextEntry::make('pickup_fee')->label('Collecte total')->numeric()->suffix(' F'),
                        TextEntry::make('pickup_distance_km')->label('Km collecte')->placeholder('—'),
                        TextEntry::make('pickup_distance_fee')->label('Collecte auto (km)')->numeric()->suffix(' F'),
                        TextEntry::make('delivery_fee')->label('Livraison total')->numeric()->suffix(' F'),
                        TextEntry::make('delivery_distance_km')->label('Km livraison')->placeholder('—'),
                        TextEntry::make('delivery_distance_fee')->label('Livraison auto (km)')->numeric()->suffix(' F'),
                        TextEntry::make('network_fee')->label('Frais réseau')->numeric()->suffix(' F'),
                        TextEntry::make('platform_fee')->label('Frais plateforme')->numeric()->suffix(' F'),
                        TextEntry::make('total_amount')->label('Total')->numeric()->suffix(' F')->weight('bold'),
                        TextEntry::make('transaction.reference')
                            ->label('Transaction')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make('Timeline de suivi')
                    ->schema([
                        RepeatableEntry::make('events')
                            ->label('')
                            ->schema([
                                TextEntry::make('status')->label('Statut')->badge(),
                                TextEntry::make('note')->label('Note')->placeholder('—'),
                                TextEntry::make('actor_type')->label('Acteur'),
                                TextEntry::make('created_at')->label('Quand')->dateTime('d/m/Y H:i'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
