<?php

namespace App\Filament\Resources\TrustedPayments\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TrustedPaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Paiement confiant')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('reference')->label('Référence'),
                        TextEntry::make('status')->label('Statut')->badge(),
                        TextEntry::make('payout_status')->label('Versement')->badge(),
                        TextEntry::make('product_description')->label('Produit')->columnSpanFull(),
                        TextEntry::make('declared_value')->label('Valeur déclarée')->numeric()->suffix(' F')->placeholder('—'),
                    ]),
                Section::make('Acteurs')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('buyer.name')->label('Acheteur'),
                        TextEntry::make('buyer_deposit_phone')->label('N° dépôt acheteur'),
                        TextEntry::make('merchant.name')->label('Commerçant'),
                        TextEntry::make('merchant_payout_phone')->label('N° versement commerçant'),
                    ]),
                Section::make('Adresses')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('pickup_address')->label('Collecte')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('pickup_district')->label('Quartier collecte')->placeholder('—'),
                        TextEntry::make('delivery_address')->label('Livraison')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('delivery_district')->label('Quartier livraison')->placeholder('—'),
                    ]),
                Section::make('Montants')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('merchandise_amount')->label('Marchandise')->numeric()->suffix(' F'),
                        TextEntry::make('network_fee')->label('Frais réseau')->numeric()->suffix(' F'),
                        TextEntry::make('platform_fee')->label('Frais plateforme')->numeric()->suffix(' F'),
                        TextEntry::make('total_amount')->label('Total acheteur')->numeric()->suffix(' F')->weight('bold'),
                        TextEntry::make('payout_amount')->label('Versement commerçant')->numeric()->suffix(' F'),
                        TextEntry::make('payout_reference')->label('Réf. versement')->placeholder('—'),
                        TextEntry::make('transaction.reference')->label('Transaction escrow')->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make('Timeline')
                    ->schema([
                        RepeatableEntry::make('events')
                            ->label('')
                            ->schema([
                                TextEntry::make('status')->label('Statut')->badge(),
                                TextEntry::make('note')->label('Note')->placeholder('—'),
                                TextEntry::make('actor_type')->label('Acteur'),
                                TextEntry::make('created_at')->label('Date')->dateTime('d/m/Y H:i'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
