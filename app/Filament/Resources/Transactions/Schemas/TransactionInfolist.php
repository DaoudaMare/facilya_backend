<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Models\Transaction;
use App\Support\Phone;
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
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reference')->label('Référence'),
                        TextEntry::make('user.name')->label('Client'),
                        TextEntry::make('type')->label('Type')->badge(),
                        TextEntry::make('amount')
                            ->label('Prix du service')
                            ->numeric()
                            ->suffix(' F CFA'),
                        TextEntry::make('network_fee')
                            ->label('Frais réseau')
                            ->numeric()
                            ->suffix(' F CFA'),
                        TextEntry::make('platform_fee')
                            ->label('Frais plateforme')
                            ->numeric()
                            ->suffix(' F CFA'),
                        TextEntry::make('total')
                            ->label('Total à payer')
                            ->state(fn (Transaction $record): string => number_format((float) $record->totalAmount(), 0, ',', ' ').' F CFA'),
                        TextEntry::make('payment_status')->label('Paiement')->badge(),
                        TextEntry::make('service_status')->label('Service')->badge(),
                        TextEntry::make('payment_receive_phone')
                            ->label('N° de réception')
                            ->state(fn (Transaction $record): string => $record->payingNetwork()?->receive_phone
                                ? Phone::format((string) $record->payingNetwork()->receive_phone)
                                : '—'),
                        TextEntry::make('payment_ussd')
                            ->label('Code USSD')
                            ->state(fn (Transaction $record): string => $record->payingNetwork()
                                ?->paymentUssdCode($record->totalAmount()) ?? '—'),
                        TextEntry::make('payment_expires_at')
                            ->label('Expire le')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ]),
                Section::make('Ticket')
                    ->columns(2)
                    ->visible(fn (Transaction $record): bool => $record->isTicketPurchase())
                    ->schema([
                        TextEntry::make('route.travelCompany.name')->label('Compagnie'),
                        TextEntry::make('route.departure')
                            ->label('Trajet')
                            ->formatStateUsing(
                                fn ($state, Transaction $record): string => $record->route
                                    ? $record->route->label()
                                    : '—',
                            ),
                        TextEntry::make('trip.station.station_name')->label('Gare'),
                        TextEntry::make('trip.departure_hour')->label('Heure de départ')->time('H:i'),
                        TextEntry::make('travel_date')->label('Date de voyage')->date(),
                        TextEntry::make('passenger_name')->label('Passager')->placeholder('—'),
                        TextEntry::make('passenger_phone')->label('Téléphone passager')->placeholder('—'),
                        TextEntry::make('passenger_count')->label('Places')->placeholder('—'),
                    ]),
                Section::make('Transfert')
                    ->columns(2)
                    ->visible(fn (Transaction $record): bool => $record->isNetworkTransfer())
                    ->schema([
                        TextEntry::make('sourceNetwork.name')->label('Réseau source'),
                        TextEntry::make('destinationNetwork.name')->label('Réseau destination'),
                        TextEntry::make('sender_phone')->label('Expéditeur'),
                        TextEntry::make('recipient_phone')->label('Destinataire'),
                        TextEntry::make('recipient_name')->label('Nom destinataire')->placeholder('—'),
                    ]),
            ]);
    }
}
