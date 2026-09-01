<?php

namespace App\Filament\Pages;

use App\Models\TransferNetwork;
use App\Support\Phone;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class PaymentReceptionSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Paiements Mobile Money';

    protected static ?string $title = 'Numéros et codes USSD de paiement';

    protected static ?string $slug = 'parametres/numeros-reception';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
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
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Paiement par réseau')
                    ->description('Pour chaque réseau, renseignez le numéro de réception et le code USSD que le client composera. Variables : {numero} et {montant}. TelRelayX rapprochera ensuite le SMS de dépôt.')
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

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($state['networks'] ?? [] as $row) {
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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Enregistrer')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }
}
