<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Filament\Support\EscrowCodesSection;
use App\Filament\Resources\ParcelShipments\ParcelShipmentResource;
use App\Filament\Resources\TrustedPayments\TrustedPaymentResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Transaction;
use App\Support\Phone;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Opération')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('reference')->label('Référence')->copyable(),
                        TextEntry::make('uuid')->label('UUID')->copyable()->placeholder('—'),
                        TextEntry::make('type')->label('Type')->badge(),
                        TextEntry::make('description')->label('Libellé')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('user.name')
                            ->label('Client')
                            ->url(fn (Transaction $record): ?string => $record->user
                                ? UserResource::getUrl('view', ['record' => $record->user])
                                : null),
                        TextEntry::make('user.phone')
                            ->label('Tél. client')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? Phone::format($state) : '—'),
                        TextEntry::make('currency')->label('Devise')->placeholder('XOF'),
                        TextEntry::make('created_at')->label('Créée le')->dateTime('d/m/Y H:i'),
                    ]),
                EscrowCodesSection::make(),
                Section::make('Paiement')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('payment_status')->label('Statut paiement')->badge(),
                        TextEntry::make('paymentNetwork.name')
                            ->label('Réseau de paiement')
                            ->placeholder(fn (Transaction $record): string => $record->payingNetwork()?->name ?? '—'),
                        TextEntry::make('payer_phone')
                            ->label('N° payeur')
                            ->state(fn (Transaction $record): string => filled($record->payerPhone())
                                ? Phone::format((string) $record->payerPhone())
                                : '—'),
                        TextEntry::make('payment_receive_phone')
                            ->label('N° de réception Facilya')
                            ->state(fn (Transaction $record): string => $record->payingNetwork()?->receive_phone
                                ? Phone::format((string) $record->payingNetwork()->receive_phone)
                                : '—'),
                        TextEntry::make('payment_ussd')
                            ->label('Code USSD')
                            ->copyable()
                            ->state(fn (Transaction $record): string => $record->payingNetwork()
                                ?->paymentUssdCode($record->totalAmount()) ?? '—'),
                        TextEntry::make('payment_reference')->label('Réf. paiement')->placeholder('—')->copyable(),
                        TextEntry::make('paid_at')->label('Payée le')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('payment_expires_at')->label('Expire le')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('payment_failure_reason')
                            ->label('Échec paiement')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make('Montants')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('amount')->label('Montant service')->numeric()->suffix(' F CFA'),
                        TextEntry::make('network_fee')->label('Frais réseau')->numeric()->suffix(' F CFA'),
                        TextEntry::make('platform_fee')->label('Frais plateforme')->numeric()->suffix(' F CFA'),
                        TextEntry::make('total')
                            ->label('Total à payer')
                            ->weight('bold')
                            ->state(fn (Transaction $record): string => number_format((float) $record->totalAmount(), 0, ',', ' ').' F CFA'),
                    ]),
                Section::make('Service')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('service_status')->label('Statut service')->badge(),
                        TextEntry::make('service_reference')->label('Réf. service')->placeholder('—')->copyable(),
                        TextEntry::make('served_at')->label('Servi le')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('service_failure_reason')
                            ->label('Échec service')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make('Ticket')
                    ->columns(3)
                    ->visible(fn (Transaction $record): bool => $record->isTicketPurchase())
                    ->schema([
                        TextEntry::make('route.travelCompany.name')->label('Compagnie')->placeholder('—'),
                        TextEntry::make('trajet')
                            ->label('Trajet')
                            ->state(fn (Transaction $record): string => $record->route?->label() ?? '—'),
                        TextEntry::make('trip.station.station_name')->label('Gare')->placeholder('—'),
                        TextEntry::make('trip.departure_hour')->label('Heure de départ')->time('H:i')->placeholder('—'),
                        TextEntry::make('travel_date')->label('Date de voyage')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('passenger_name')->label('Passager')->placeholder('—'),
                        TextEntry::make('passenger_phone')
                            ->label('Tél. passager')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? Phone::format($state) : '—'),
                        TextEntry::make('passenger_count')->label('Places')->placeholder('—'),
                    ]),
                Section::make('Transfert')
                    ->columns(3)
                    ->visible(fn (Transaction $record): bool => $record->isNetworkTransfer())
                    ->schema([
                        TextEntry::make('sourceNetwork.name')->label('Réseau source')->placeholder('—'),
                        TextEntry::make('destinationNetwork.name')->label('Réseau destination')->placeholder('—'),
                        TextEntry::make('sender_phone')
                            ->label('Expéditeur')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? Phone::format($state) : '—'),
                        TextEntry::make('recipient_phone')
                            ->label('Destinataire')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? Phone::format($state) : '—'),
                        TextEntry::make('recipient_name')->label('Nom destinataire')->placeholder('—'),
                    ]),
                Section::make('Colis')
                    ->columns(3)
                    ->visible(fn (Transaction $record): bool => $record->isParcelShipment() && $record->parcelShipment !== null)
                    ->schema([
                        TextEntry::make('parcelShipment.reference')
                            ->label('Référence colis')
                            ->copyable()
                            ->url(fn (Transaction $record): ?string => $record->parcelShipment
                                ? ParcelShipmentResource::getUrl('view', ['record' => $record->parcelShipment])
                                : null),
                        TextEntry::make('parcelShipment.status')->label('Suivi colis')->badge(),
                        TextEntry::make('parcelShipment.scope')->label('Type')->badge(),
                        TextEntry::make('parcelShipment.delivery_mode')->label('Mode')->badge(),
                        TextEntry::make('parcelShipment.origin_city')->label('Origine')->placeholder('—'),
                        TextEntry::make('parcelShipment.destination_city')->label('Destination')->placeholder('—'),
                        TextEntry::make('parcelShipment.travelCompany.name')->label('Compagnie')->placeholder('—'),
                        TextEntry::make('parcelShipment.parcel_description')->label('Description')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('parcelShipment.estimated_weight_kg')->label('Poids (kg)')->placeholder('—'),
                        TextEntry::make('parcelShipment.declared_value')->label('Valeur déclarée')->numeric()->suffix(' F')->placeholder('—'),
                        TextEntry::make('parcelShipment.trustedPayment.public_id')
                            ->label('Escrow lié')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('parcelShipment.sender_name')->label('Expéditeur'),
                        TextEntry::make('parcelShipment.sender_phone')
                            ->label('Tél. expéditeur')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? Phone::format($state) : '—'),
                        TextEntry::make('parcelShipment.sender_cnib_number')->label('CNIB expéditeur')->placeholder('—'),
                        TextEntry::make('parcelShipment.pickup_code')->label('Code collecte')->copyable()->placeholder('—'),
                        TextEntry::make('parcelShipment.pickup_address')
                            ->label('Maps collecte')
                            ->placeholder('—')
                            ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                        TextEntry::make('parcelShipment.recipient_name')->label('Destinataire'),
                        TextEntry::make('parcelShipment.recipient_phone')
                            ->label('Tél. destinataire')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? Phone::format($state) : '—'),
                        TextEntry::make('parcelShipment.recipient_cnib_number')->label('CNIB destinataire')->placeholder('—'),
                        TextEntry::make('parcelShipment.delivery_code')->label('Code livraison')->copyable()->placeholder('—'),
                        TextEntry::make('parcelShipment.station_pickup_code')->label('Code retrait gare')->copyable()->placeholder('—'),
                        TextEntry::make('parcelShipment.recipient_address')
                            ->label('Maps livraison')
                            ->placeholder('—')
                            ->url(fn (?string $state): ?string => filled($state) ? $state : null)
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
                        TextEntry::make('parcelShipment.agency_fee')->label('Frais agence')->numeric()->suffix(' F')->placeholder('—'),
                        TextEntry::make('parcelShipment.pickup_fee')->label('Collecte')->numeric()->suffix(' F')->placeholder('—'),
                        TextEntry::make('parcelShipment.delivery_fee')->label('Livraison')->numeric()->suffix(' F')->placeholder('—'),
                        TextEntry::make('parcelShipment.pickup_distance_km')->label('Km collecte')->placeholder('—'),
                        TextEntry::make('parcelShipment.delivery_distance_km')->label('Km livraison')->placeholder('—'),
                        TextEntry::make('parcelShipment.total_amount')->label('Total colis')->numeric()->suffix(' F')->weight('bold'),
                        ImageEntry::make('parcelShipment.parcel_photo')
                            ->label('Photo du colis')
                            ->disk('public')
                            ->columnSpanFull(),
                        ImageEntry::make('parcelShipment.sender_cnib_photo')
                            ->label('CNIB expéditeur')
                            ->disk('public'),
                        ImageEntry::make('parcelShipment.recipient_cnib_photo')
                            ->label('CNIB destinataire')
                            ->disk('public'),
                    ]),
                Section::make('Suivi colis')
                    ->visible(fn (Transaction $record): bool => $record->parcelShipment?->events?->isNotEmpty() ?? false)
                    ->schema([
                        RepeatableEntry::make('parcelShipment.events')
                            ->label('')
                            ->schema([
                                TextEntry::make('status')->label('Statut')->badge(),
                                TextEntry::make('note')->label('Note')->placeholder('—'),
                                TextEntry::make('actor_type')->label('Acteur')->placeholder('—'),
                                TextEntry::make('created_at')->label('Date')->dateTime('d/m/Y H:i'),
                            ])
                            ->columns(4),
                    ]),
                Section::make('Paiement confiant')
                    ->columns(3)
                    ->visible(fn (Transaction $record): bool => $record->isTrustedPayment() && $record->trustedPayment !== null)
                    ->schema([
                        TextEntry::make('trustedPayment.reference')
                            ->label('Référence')
                            ->copyable()
                            ->url(fn (Transaction $record): ?string => $record->trustedPayment
                                ? TrustedPaymentResource::getUrl('view', ['record' => $record->trustedPayment])
                                : null),
                        TextEntry::make('trustedPayment.public_id')->label('Identifiant (6 chiffres)')->copyable(),
                        TextEntry::make('trustedPayment.status')->label('Statut escrow')->badge(),
                        TextEntry::make('trustedPayment.payout_status')->label('Versement')->badge(),
                        TextEntry::make('trustedPayment.product_description')->label('Produit / service')->columnSpanFull(),
                        TextEntry::make('trustedPayment.buyer.name')->label('Client (acheteur)'),
                        TextEntry::make('trustedPayment.buyer.phone')
                            ->label('Tél. client')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? Phone::format($state) : '—'),
                        TextEntry::make('trustedPayment.buyer_deposit_phone')
                            ->label('N° dépôt')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? Phone::format($state) : '—'),
                        TextEntry::make('trustedPayment.merchant.name')->label('Commerçant'),
                        TextEntry::make('trustedPayment.merchant.phone')
                            ->label('Tél. commerçant')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? Phone::format($state) : '—'),
                        TextEntry::make('trustedPayment.merchant_payout_phone')
                            ->label('N° versement')
                            ->formatStateUsing(fn (?string $state): string => filled($state) ? Phone::format($state) : '—'),
                        TextEntry::make('trustedPayment.merchandise_amount')->label('Montant bloqué')->numeric()->suffix(' F'),
                        TextEntry::make('trustedPayment.payout_amount')->label('Versement prévu')->numeric()->suffix(' F'),
                        TextEntry::make('trustedPayment.payout_reference')->label('Réf. versement')->placeholder('—')->copyable(),
                        TextEntry::make('trustedPayment.parcelShipment.reference')
                            ->label('Colis lié')
                            ->placeholder('—')
                            ->url(fn (Transaction $record): ?string => $record->trustedPayment?->parcelShipment
                                ? ParcelShipmentResource::getUrl('view', ['record' => $record->trustedPayment->parcelShipment])
                                : null),
                        TextEntry::make('trustedPayment.pickup_address')->label('Collecte')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('trustedPayment.delivery_address')->label('Livraison')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('trustedPayment.funds_held_at')->label('Fonds bloqués le')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('trustedPayment.collected_at')->label('Collecté le')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('trustedPayment.payout_sent_at')->label('Versé le')->dateTime('d/m/Y H:i')->placeholder('—'),
                    ]),
                Section::make('Suivi paiement confiant')
                    ->visible(fn (Transaction $record): bool => $record->trustedPayment?->events?->isNotEmpty() ?? false)
                    ->schema([
                        RepeatableEntry::make('trustedPayment.events')
                            ->label('')
                            ->schema([
                                TextEntry::make('status')->label('Statut')->badge(),
                                TextEntry::make('note')->label('Note')->placeholder('—'),
                                TextEntry::make('actor_type')->label('Acteur')->placeholder('—'),
                                TextEntry::make('created_at')->label('Date')->dateTime('d/m/Y H:i'),
                            ])
                            ->columns(4),
                    ]),
                Section::make('Jobs relais')
                    ->visible(fn (Transaction $record): bool => $record->relayJobs->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('relayJobs')
                            ->label('')
                            ->schema([
                                TextEntry::make('type')->label('Type')->badge(),
                                TextEntry::make('status')->label('Statut')->badge(),
                                TextEntry::make('network')->label('Réseau')->placeholder('—'),
                                TextEntry::make('recipient_phone')
                                    ->label('Bénéficiaire')
                                    ->formatStateUsing(fn (?string $state): string => filled($state) ? Phone::format($state) : '—'),
                                TextEntry::make('amount')->label('Montant')->numeric()->suffix(' F'),
                                TextEntry::make('provider_reference')->label('Réf. opérateur')->placeholder('—'),
                                TextEntry::make('failure_reason')->label('Échec')->placeholder('—'),
                                TextEntry::make('completed_at')->label('Terminé le')->dateTime('d/m/Y H:i')->placeholder('—'),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }
}
