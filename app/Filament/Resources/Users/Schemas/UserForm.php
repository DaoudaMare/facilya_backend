<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\GoogleMapsLink;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->columns(2)
                    ->schema([
                        Hidden::make('name'),
                        TextInput::make('first_name')
                            ->label('Prénom')
                            ->maxLength(60),
                        TextInput::make('last_name')
                            ->label('Nom')
                            ->maxLength(60),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),
                        Select::make('role_id')
                            ->label('Rôle')
                            ->relationship('role', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                    ]),
                Section::make('Téléphones')
                    ->description('Deux numéros maximum (principal + secondaire).')
                    ->columns(2)
                    ->schema([
                        TextInput::make('phone')
                            ->label('Téléphone principal')
                            ->tel()
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone_secondary')
                            ->label('Téléphone secondaire')
                            ->tel()
                            ->unique(ignoreRecord: true),
                    ]),
                Section::make('Pièce d’identité')
                    ->description('Réutilisée à l’envoi de colis (expéditeur).')
                    ->columns(2)
                    ->schema([
                        TextInput::make('cnib_number')
                            ->label('N° CNIB')
                            ->maxLength(64),
                        FileUpload::make('cnib_photo')
                            ->label('Photo CNIB')
                            ->image()
                            ->disk('public')
                            ->directory('users/cnib')
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),
                Section::make('Adresses')
                    ->description('Nom libre + lien Google Maps. Utilisées à l’envoi de colis.')
                    ->schema([
                        Repeater::make('addresses')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nom de l’adresse')
                                    ->required()
                                    ->maxLength(80)
                                    ->placeholder('Ex. Domicile, Boutique centre'),
                                TextInput::make('maps_url')
                                    ->label('Lien Google Maps')
                                    ->required()
                                    ->url()
                                    ->maxLength(2048)
                                    ->placeholder('https://maps.app.goo.gl/...')
                                    ->columnSpanFull()
                                    ->rules([
                                        fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                            if (filled($value) && ! GoogleMapsLink::isValid((string) $value)) {
                                                $fail(GoogleMapsLink::validationMessage());
                                            }
                                        },
                                    ]),
                            ])
                            ->columns(1)
                            ->addActionLabel('Ajouter une adresse')
                            ->maxItems(8)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null) ? (string) $state['name'] : null),
                    ]),
                Section::make('Accès')
                    ->schema([
                        TextInput::make('password')
                            ->label('Mot de passe')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ]),
            ]);
    }
}
