<?php

namespace App\Filament\Resources\Promotions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contenu')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Titre')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('subtitle')
                            ->label('Sous-titre')
                            ->maxLength(180),
                        FileUpload::make('image')
                            ->label('Image')
                            ->image()
                            ->disk('public')
                            ->directory('promotions')
                            ->imageEditor()
                            ->imageCropAspectRatio('16:9')
                            ->maxSize(4096)
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('link_url')
                            ->label('Lien (optionnel)')
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Section::make('Diffusion')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sort_order')
                            ->label('Ordre')
                            ->numeric()
                            ->default(0)
                            ->helperText('Plus petit = affiché en premier.'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        DateTimePicker::make('starts_at')
                            ->label('Début')
                            ->seconds(false),
                        DateTimePicker::make('ends_at')
                            ->label('Fin')
                            ->seconds(false),
                    ]),
            ]);
    }
}
