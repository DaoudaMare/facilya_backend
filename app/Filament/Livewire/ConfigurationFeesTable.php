<?php

namespace App\Filament\Livewire;

use App\Filament\Resources\Fees\FeeResource;
use App\Filament\Resources\Fees\Tables\FeesTable;
use App\Models\Fee;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ConfigurationFeesTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return FeesTable::configure($table)
            ->query(Fee::query())
            ->headerActions([
                CreateAction::make()
                    ->label('Nouveau frais')
                    ->url(FeeResource::getUrl('create')),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn (Fee $record): string => FeeResource::getUrl('edit', ['record' => $record])),
            ]);
    }

    public function render(): View
    {
        return view('livewire.configuration-fees-table');
    }
}
