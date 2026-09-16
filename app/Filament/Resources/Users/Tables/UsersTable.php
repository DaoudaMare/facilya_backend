<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name')
                    ->label('Prénom')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('last_name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Tél. principal')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('phone_secondary')
                    ->label('Tél. secondaire')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('addresses_count')
                    ->label('Adresses')
                    ->counts('addresses')
                    ->sortable(),
                TextColumn::make('profile')
                    ->label('Profil')
                    ->badge()
                    ->getStateUsing(function (User $record): string {
                        return $record->isProfileComplete() ? 'Complet' : 'Incomplet';
                    })
                    ->color(fn (string $state): string => $state === 'Complet' ? 'success' : 'warning'),
                TextColumn::make('role.name')
                    ->label('Rôle')
                    ->badge()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role_id')
                    ->label('Rôle')
                    ->relationship('role', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
