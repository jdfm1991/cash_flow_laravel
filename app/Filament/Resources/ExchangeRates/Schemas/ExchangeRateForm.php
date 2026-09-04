<?php

namespace App\Filament\Resources\ExchangeRates\Schemas;

use App\Models\Currency;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExchangeRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la tasa')
                    ->schema([
                        Select::make('from_currency_id')
                            ->required()
                            ->label('Moneda origen')
                            ->options(fn () => Currency::where('is_active', true)
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Moneda desde la que se convierte'),
                        
                        Select::make('to_currency_id')
                            ->required()
                            ->label('Moneda destino')
                            ->options(fn () => Currency::where('is_active', true)
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Moneda a la que se convierte')
                            ->rule('different:from_currency_id'),
                        
                        TextInput::make('rate')
                            ->required()
                            ->numeric()
                            ->minValue(0.00000001)
                            ->step(0.00000001)
                            ->label('Tasa de cambio')
                            ->helperText('Cantidad de moneda destino por 1 unidad de moneda origen')
                            ->placeholder('Ej: 517.96000000'),
                        
                        DatePicker::make('effective_date')
                            ->required()
                            ->default(now())
                            ->label('Fecha efectiva')
                            ->helperText('Fecha a partir de la cual aplica esta tasa')
                            ->maxDate(now()),
                    ])->columns(2),

                Section::make('Información adicional')
                    ->schema([
                        Select::make('source')
                            ->options([
                                'manual' => 'Manual',
                                'api' => 'API',
                                'system' => 'Sistema',
                            ])
                            ->default('manual')
                            ->label('Fuente de la tasa'),
                        
                        Textarea::make('notes')
                            ->maxLength(500)
                            ->label('Notas')
                            ->rows(3)
                            ->placeholder('Información adicional sobre la tasa de cambio...'),
                        
                        Toggle::make('is_current')
                            ->default(true)
                            ->label('Tasa actual')
                            ->helperText('Marcar como la tasa más reciente para este par de monedas'),
                    ])->columns(2),
            ])
            ->columns(1);
    }
}
