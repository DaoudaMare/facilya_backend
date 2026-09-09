<?php

namespace App\Filament\Pages;

use App\Models\ReferralSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
class ReferralSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Parrainage';

    protected static ?string $title = 'Paramètres de parrainage';

    protected static ?string $slug = 'parametres/parrainage';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = ReferralSetting::current();

        $this->form->fill([
            'is_active' => $settings->is_active,
            'commission_percent' => (float) $settings->commission_percent,
            'max_rewarded_transactions' => (int) $settings->max_rewarded_transactions,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
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

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = ReferralSetting::current();

        $settings->fill([
            'is_active' => (bool) ($data['is_active'] ?? false),
            'commission_percent' => (float) ($data['commission_percent'] ?? 0.5),
            'max_rewarded_transactions' => (int) ($data['max_rewarded_transactions'] ?? 10),
        ])->save();

        Notification::make()
            ->title('Paramètres de parrainage enregistrés')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
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
