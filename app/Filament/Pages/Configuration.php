<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ParcelPricingSettings\Schemas\ParcelPricingSettingForm;
use App\Models\AppSetting;
use App\Models\ParcelPricingSetting;
use App\Models\ReferralSetting;
use App\Models\TransferNetwork;
use App\Models\User;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * @property-read Schema $paymentsForm
 * @property-read Schema $supportForm
 * @property-read Schema $referralForm
 * @property-read Schema $parcelForm
 */
class Configuration extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Configuration';

    protected static ?string $title = 'Configuration';

    protected static ?string $slug = 'configuration';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $paymentsData = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $supportData = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $referralData = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $parcelData = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasAnyPermission(
            'settings.manage',
            'fees.manage',
            'parcels.manage',
        );
    }

    public static function tabUrl(string $tab): string
    {
        return static::getUrl().'?tab='.$tab;
    }

    public function mount(): void
    {
        $user = Auth::user();
        $canSettings = $user instanceof User && $user->hasPermission('settings.manage');
        $canParcels = $user instanceof User && $user->hasPermission('parcels.manage');

        if ($canSettings) {
            $this->paymentsForm->fill([
                'networks' => TransferNetwork::query()
                    ->orderBy('name')
                    ->get()
                    ->map(fn (TransferNetwork $network): array => [
                        'id' => $network->id,
                        'name' => $network->name,
                        'code' => $network->code?->value ?? (string) $network->code,
                        'receive_phone' => $network->receive_phone,
                        'payment_ussd' => $network->payment_ussd,
                    ])
                    ->all(),
            ]);

            $this->supportForm->fill([
                'support_whatsapp_phone' => AppSetting::current()->support_whatsapp_phone,
            ]);

            $referral = ReferralSetting::current();
            $this->referralForm->fill([
                'is_active' => $referral->is_active,
                'commission_percent' => (float) $referral->commission_percent,
                'max_rewarded_transactions' => (int) $referral->max_rewarded_transactions,
            ]);
        }

        if ($canParcels) {
            $pricing = ParcelPricingSetting::current();
            $this->parcelForm->fill([
                'agency_mode' => $pricing->agency_mode,
                'agency_value' => $pricing->agency_value,
                'margin_mode' => $pricing->margin_mode,
                'margin_value' => $pricing->margin_value,
                'pickup_mode' => $pricing->pickup_mode,
                'pickup_value' => $pricing->pickup_value,
                'pickup_per_km' => $pricing->pickup_per_km,
                'delivery_mode' => $pricing->delivery_mode,
                'delivery_value' => $pricing->delivery_value,
                'delivery_per_km' => $pricing->delivery_per_km,
                'value_fee_percent' => $pricing->value_fee_percent,
            ]);
        }
    }

    public function paymentsForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('paymentsData')
            ->components([
                Section::make('Paiement par réseau')
                    ->description('Pour chaque réseau, renseignez le numéro de réception et le code USSD que le client composera. Variables : {numero} et {montant}.')
                    ->schema([
                        Repeater::make('networks')
                            ->hiddenLabel()
                            ->schema([
                                Hidden::make('id'),
                                TextInput::make('name')
                                    ->label('Réseau')
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('code')
                                    ->label('Code')
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('receive_phone')
                                    ->label('Numéro de réception')
                                    ->tel()
                                    ->maxLength(32)
                                    ->placeholder('70 00 00 00'),
                                TextInput::make('payment_ussd')
                                    ->label('Code USSD de paiement')
                                    ->maxLength(120)
                                    ->placeholder('*144*1*1*{numero}*{montant}#')
                                    ->helperText('Exemple Orange : *144*1*1*{numero}*{montant}#')
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                    ]),
            ]);
    }

    public function supportForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('supportData')
            ->components([
                Section::make('Contact client')
                    ->description('Ce numéro est renvoyé à l’application. Le bouton « Contacter le support » ouvre WhatsApp.')
                    ->schema([
                        TextInput::make('support_whatsapp_phone')
                            ->label('Numéro WhatsApp')
                            ->tel()
                            ->placeholder('70 11 11 11')
                            ->helperText('Numéro burkinabè, 8 chiffres. L’app construira le lien wa.me.'),
                    ]),
            ]);
    }

    public function referralForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('referralData')
            ->components([
                Section::make('Commission parrain')
                    ->description('Seul le parrain gagne. La commission est calculée sur le montant du service (hors frais), pour les N premières transactions livrées du filleul.')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Programme actif')
                            ->helperText('Si désactivé, aucun nouveau lien ni aucune commission.'),
                        TextInput::make('commission_percent')
                            ->label('Pourcentage de commission (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.0001)
                            ->required()
                            ->helperText('Exemple : 0,5 = 0,5 % du montant (10 000 F → 50 F).'),
                        TextInput::make('max_rewarded_transactions')
                            ->label('Nombre de transactions récompensées')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->required()
                            ->helperText('Ex. 10 = commission sur les 10 premières transactions réussies du filleul.'),
                    ]),
            ]);
    }

    public function parcelForm(Schema $schema): Schema
    {
        return ParcelPricingSettingForm::configure($schema->statePath('parcelData'));
    }

    public function savePayments(): void
    {
        $this->assertPermission('settings.manage');

        foreach ($this->paymentsForm->getState()['networks'] ?? [] as $row) {
            $network = TransferNetwork::query()->find($row['id'] ?? null);
            if (! $network) {
                continue;
            }

            $phone = trim((string) ($row['receive_phone'] ?? ''));
            $ussd = trim((string) ($row['payment_ussd'] ?? ''));
            $network->update([
                'receive_phone' => $phone === '' ? null : Phone::normalize($phone),
                'payment_ussd' => $ussd === '' ? null : $ussd,
            ]);
        }

        Notification::make()
            ->title('Paramètres de paiement enregistrés')
            ->success()
            ->send();
    }

    public function saveSupport(): void
    {
        $this->assertPermission('settings.manage');

        $raw = trim((string) ($this->supportForm->getState()['support_whatsapp_phone'] ?? ''));
        $phone = $raw === '' ? null : Phone::normalize($raw);

        if ($phone !== null && ! Phone::isValid($phone)) {
            Notification::make()
                ->title('Numéro WhatsApp invalide')
                ->danger()
                ->send();

            return;
        }

        AppSetting::current()->fill([
            'support_whatsapp_phone' => $phone,
        ])->save();

        Notification::make()
            ->title('Numéro WhatsApp du support enregistré')
            ->success()
            ->send();
    }

    public function saveReferral(): void
    {
        $this->assertPermission('settings.manage');

        $data = $this->referralForm->getState();
        ReferralSetting::current()->fill([
            'is_active' => (bool) ($data['is_active'] ?? false),
            'commission_percent' => (float) ($data['commission_percent'] ?? 0.5),
            'max_rewarded_transactions' => (int) ($data['max_rewarded_transactions'] ?? 10),
        ])->save();

        Notification::make()
            ->title('Paramètres de parrainage enregistrés')
            ->success()
            ->send();
    }

    public function saveParcel(): void
    {
        $this->assertPermission('parcels.manage');

        ParcelPricingSetting::current()->fill($this->parcelForm->getState())->save();

        Notification::make()
            ->title('Tarifs colis enregistrés')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        $user = Auth::user();
        $canSettings = $user instanceof User && $user->hasPermission('settings.manage');
        $canFees = $user instanceof User && $user->hasPermission('fees.manage');
        $canParcels = $user instanceof User && $user->hasPermission('parcels.manage');

        $tabs = [];

        if ($canSettings) {
            $tabs[] = Tab::make('Mobile Money')
                ->id('mobile-money')
                ->icon(Heroicon::OutlinedDevicePhoneMobile)
                ->schema([
                    Form::make([EmbeddedSchema::make('paymentsForm')])
                        ->id('paymentsForm')
                        ->livewireSubmitHandler('savePayments')
                        ->footer([
                            Actions::make([
                                Action::make('savePayments')
                                    ->label('Enregistrer')
                                    ->submit('savePayments'),
                            ]),
                        ]),
                ]);

            $tabs[] = Tab::make('Support WhatsApp')
                ->id('support')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->schema([
                    Form::make([EmbeddedSchema::make('supportForm')])
                        ->id('supportForm')
                        ->livewireSubmitHandler('saveSupport')
                        ->footer([
                            Actions::make([
                                Action::make('saveSupport')
                                    ->label('Enregistrer')
                                    ->submit('saveSupport'),
                            ]),
                        ]),
                ]);

            $tabs[] = Tab::make('Parrainage')
                ->id('parrainage')
                ->icon(Heroicon::OutlinedGift)
                ->schema([
                    Form::make([EmbeddedSchema::make('referralForm')])
                        ->id('referralForm')
                        ->livewireSubmitHandler('saveReferral')
                        ->footer([
                            Actions::make([
                                Action::make('saveReferral')
                                    ->label('Enregistrer')
                                    ->submit('saveReferral'),
                            ]),
                        ]),
                ]);
        }

        if ($canFees) {
            $tabs[] = Tab::make('Frais')
                ->id('frais')
                ->icon(Heroicon::OutlinedReceiptPercent)
                ->schema([
                    Livewire::make(\App\Filament\Livewire\ConfigurationFeesTable::class),
                ]);
        }

        if ($canParcels) {
            $tabs[] = Tab::make('Tarifs colis')
                ->id('tarifs-colis')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->schema([
                    Form::make([EmbeddedSchema::make('parcelForm')])
                        ->id('parcelForm')
                        ->livewireSubmitHandler('saveParcel')
                        ->footer([
                            Actions::make([
                                Action::make('saveParcel')
                                    ->label('Enregistrer')
                                    ->submit('saveParcel'),
                            ]),
                        ]),
                ]);
        }

        return $schema->components([
            Tabs::make('configuration')
                ->persistTabInQueryString()
                ->contained(false)
                ->tabs($tabs),
        ]);
    }

    protected function assertPermission(string $permission): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->hasPermission($permission), 403);
    }
}
