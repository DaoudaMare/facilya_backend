<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Client')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('first_name')->label('Prénom')->placeholder('—'),
                        TextEntry::make('last_name')->label('Nom')->placeholder('—'),
                        TextEntry::make('name')->label('Nom affiché'),
                        TextEntry::make('email')->label('Email')->copyable(),
                        TextEntry::make('phone')->label('Tél. principal')->placeholder('—')->copyable(),
                        TextEntry::make('phone_secondary')->label('Tél. secondaire')->placeholder('—')->copyable(),
                        TextEntry::make('role.name')->label('Rôle')->badge()->placeholder('—'),
                        TextEntry::make('profile')
                            ->label('Profil colis')
                            ->badge()
                            ->getStateUsing(function (User $record): string {
                                $record->loadMissing('addresses');

                                return $record->isProfileComplete() ? 'Complet' : 'Incomplet';
                            })
                            ->color(fn (string $state): string => $state === 'Complet' ? 'success' : 'warning'),
                        TextEntry::make('referral_code')->label('Code parrainage')->placeholder('—')->copyable(),
                    ]),
                Section::make('Pièce d’identité')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('cnib_number')->label('N° CNIB')->placeholder('—')->copyable(),
                        ImageEntry::make('cnib_photo')
                            ->label('Photo CNIB')
                            ->disk('public')
                            ->columnSpanFull(),
                    ]),
                Section::make('Adresses')
                    ->schema([
                        RepeatableEntry::make('addresses')
                            ->hiddenLabel()
                            ->placeholder('Aucune adresse enregistrée')
                            ->schema([
                                TextEntry::make('name')->label('Nom'),
                                TextEntry::make('maps_url')
                                    ->label('Google Maps')
                                    ->url(fn ($state) => filled($state) ? (string) $state : null)
                                    ->openUrlInNewTab(),
                            ]),
                    ]),
            ]);
    }
}
