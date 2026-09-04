<?php

namespace App\Filament\Resources\Currencies\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;

class CurrencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                   Section::make('Información de la moneda')
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(3)
                            ->unique(ignoreRecord: true)
                            ->helperText('Código ISO 4217 (USD, EUR, VES, COP)'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('symbol')
                            ->required()
                            ->maxLength(5),
                        Select::make('decimal_places')
                            ->options([
                                0 => '0',
                                1 => '1',
                                2 => '2',
                                3 => '3',
                                4 => '4',
                            ])
                            ->default(2)
                            ->required(),
                        Toggle::make('is_base')
                            ->label('Moneda base del sistema')
                            ->helperText('La moneda base se usa para todos los reportes'),
                        Toggle::make('is_default')
                            ->label('Moneda por defecto para visualización'),
                        Toggle::make('is_active')
                            ->default(true)
                            ->label('Activa'),
                    ])->columns(2),
            ])
            ->columns(1);
    }
}
