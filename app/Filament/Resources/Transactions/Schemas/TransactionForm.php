<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Data\PaymentStatusEnum;
use App\Data\ServiceStatusEnum;
use App\Data\TransactionTypeEnum;
use App\Models\TravelCompanyRoute;
use App\Models\TravelCompanyTrip;
use App\Services\TransactionService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Opération')
                    ->columns(2)
                    ->schema([
                        TextInput::make('reference')
                            ->label('Référence')
                            ->disabled()
                            ->dehydrated()
                            ->visibleOn(['edit', 'view']),
                        Select::make('user_id')
                            ->label('Client')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('type')
                            ->label('Type')
                            ->options(TransactionTypeEnum::class)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalculateAmounts($set, $get)),
                        TextInput::make('amount')
                            ->label('Prix du service')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->suffix('F CFA')
                            ->disabled(fn (Get $get): bool => self::isTicket($get('type')))
                            ->dehydrated()
                            ->live(onBlur: true)
                            ->helperText(fn (Get $get): string => self::isTicket($get('type'))
                                ? 'Prix du trajet × nombre de places. Les frais en % s’appliquent sur ce montant.'
                                : 'Montant transféré. Les frais en % s’appliquent sur ce montant.')
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::quoteFees($set, $get)),
                        TextInput::make('currency')
                            ->label('Devise')
                            ->default('XOF')
                            ->required()
                            ->maxLength(3),
                        Select::make('payment_network_id')
                            ->label('Réseau de paiement')
                            ->relationship('paymentNetwork', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::quoteFees($set, $get)),
                        Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ]),
                Section::make('Achat de ticket')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => self::isTicket($get('type')))
                    ->schema([
                        Select::make('travel_company_route_id')
                            ->label('Trajet')
                            ->relationship(
                                'route',
                                'departure',
                                fn (Builder $query) => $query
                                    ->with('travelCompany')
                                    ->where('is_active', true),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (TravelCompanyRoute $record): string => $record->label(),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(fn (Get $get): bool => self::isTicket($get('type')))
                            ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                                $set('travel_company_trip_id', null);
                                self::recalculateAmounts($set, $get, $state);
                            }),
                        Select::make('travel_company_trip_id')
                            ->label('Horaire')
                            ->relationship(
                                'trip',
                                'id',
                                function (Builder $query, Get $get): void {
                                    $query->with(['station', 'route.travelCompany']);

                                    $routeId = $get('travel_company_route_id');

                                    if (blank($routeId)) {
                                        $query->whereRaw('1 = 0');

                                        return;
                                    }

                                    $query
                                        ->where('travel_company_route_id', $routeId)
                                        ->where('is_active', true);
                                },
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (TravelCompanyTrip $record): string => sprintf(
                                    '%s — %s (%s)',
                                    substr((string) $record->departure_hour, 0, 5),
                                    $record->station?->station_name,
                                    $record->arrival_hour
                                        ? 'arrivée '.substr((string) $record->arrival_hour, 0, 5)
                                        : 'horaire',
                                ),
                            )
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get): bool => blank($get('travel_company_route_id')))
                            ->required(fn (Get $get): bool => self::isTicket($get('type')))
                            ->helperText('Choisis d’abord un trajet, puis l’horaire correspondant.'),
                        DatePicker::make('travel_date')
                            ->label('Date de voyage')
                            ->required(fn (Get $get): bool => self::isTicket($get('type'))),
                        TextInput::make('passenger_name')
                            ->label('Passager'),
                        TextInput::make('passenger_phone')
                            ->label('Téléphone passager')
                            ->tel(),
                        TextInput::make('passenger_count')
                            ->label('Nombre de places')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::recalculateAmounts($set, $get)),
                    ]),
                Section::make('Transfert inter-réseau')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => self::isTransfer($get('type')))
                    ->schema([
                        Select::make('source_network_id')
                            ->label('Réseau source')
                            ->relationship('sourceNetwork', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(fn (Get $get): bool => self::isTransfer($get('type')))
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::quoteFees($set, $get)),
                        Select::make('destination_network_id')
                            ->label('Réseau destination')
                            ->relationship('destinationNetwork', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(fn (Get $get): bool => self::isTransfer($get('type')))
                            ->different('source_network_id')
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::quoteFees($set, $get)),
                        TextInput::make('sender_phone')
                            ->label('Téléphone expéditeur')
                            ->tel()
                            ->required(fn (Get $get): bool => self::isTransfer($get('type'))),
                        TextInput::make('recipient_phone')
                            ->label('Téléphone destinataire')
                            ->tel()
                            ->required(fn (Get $get): bool => self::isTransfer($get('type'))),
                        TextInput::make('recipient_name')
                            ->label('Nom du destinataire'),
                    ]),
                Section::make('Frais')
                    ->description('Calculés automatiquement à partir des règles (montant fixe ou pourcentage du prix du service).')
                    ->columns(2)
                    ->schema([
                        TextInput::make('network_fee')
                            ->label('Frais réseau')
                            ->numeric()
                            ->suffix('F CFA')
                            ->disabled()
                            ->dehydrated()
                            ->default(0),
                        TextInput::make('platform_fee')
                            ->label('Frais plateforme')
                            ->numeric()
                            ->suffix('F CFA')
                            ->disabled()
                            ->dehydrated()
                            ->default(0),
                        Placeholder::make('total_a_payer')
                            ->label('Total à payer')
                            ->content(function (Get $get): string {
                                $total = bcadd(
                                    bcadd((string) ($get('amount') ?: '0'), (string) ($get('network_fee') ?: '0'), 2),
                                    (string) ($get('platform_fee') ?: '0'),
                                    2,
                                );

                                return number_format((float) $total, 0, ',', ' ').' F CFA';
                            })
                            ->columnSpanFull(),
                    ]),
                Section::make('Statuts')
                    ->columns(2)
                    ->visibleOn(['edit', 'view'])
                    ->schema([
                        Select::make('payment_status')
                            ->label('Statut paiement')
                            ->options(PaymentStatusEnum::class)
                            ->default(PaymentStatusEnum::PENDING)
                            ->required(),
                        Select::make('service_status')
                            ->label('Statut service')
                            ->options(ServiceStatusEnum::class)
                            ->default(ServiceStatusEnum::PENDING)
                            ->required(),
                        TextInput::make('payment_reference')
                            ->label('Réf. paiement'),
                        TextInput::make('service_reference')
                            ->label('Réf. service'),
                        Textarea::make('payment_failure_reason')
                            ->label('Motif échec paiement')
                            ->columnSpanFull(),
                        Textarea::make('service_failure_reason')
                            ->label('Motif échec service')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function isTicket(mixed $type): bool
    {
        return $type === TransactionTypeEnum::TICKET_PURCHASE
            || $type === TransactionTypeEnum::TICKET_PURCHASE->value;
    }

    protected static function isTransfer(mixed $type): bool
    {
        return $type === TransactionTypeEnum::NETWORK_TRANSFER
            || $type === TransactionTypeEnum::NETWORK_TRANSFER->value;
    }

    protected static function quoteFees(Set $set, Get $get, ?string $amount = null): void
    {
        self::applyQuote($set, $get, amount: $amount);
    }

    protected static function recalculateAmounts(Set $set, Get $get, mixed $routeId = null): void
    {
        self::applyQuote($set, $get, routeId: $routeId);
    }

    protected static function applyQuote(Set $set, Get $get, mixed $routeId = null, ?string $amount = null): void
    {
        $quote = app(TransactionService::class)->quoteFromState([
            'type' => $get('type'),
            'amount' => $amount ?? $get('amount'),
            'travel_company_route_id' => $routeId ?? $get('travel_company_route_id'),
            'passenger_count' => $get('passenger_count'),
            'payment_network_id' => $get('payment_network_id'),
            'source_network_id' => $get('source_network_id'),
            'destination_network_id' => $get('destination_network_id'),
        ]);

        if (self::isTicket($get('type')) && filled($routeId ?? $get('travel_company_route_id'))) {
            $set('amount', $quote->serviceAmount);
            $set('passenger_count', max(1, (int) ($get('passenger_count') ?: 1)));
        }

        $set('network_fee', $quote->networkFee);
        $set('platform_fee', $quote->platformFee);
    }
}
