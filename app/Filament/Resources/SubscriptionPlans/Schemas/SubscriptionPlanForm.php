<?php

namespace App\Filament\Resources\SubscriptionPlans\Schemas;

use App\Models\Currency;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubscriptionPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del plan')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->label('Nombre del plan')
                            ->placeholder('Ej: Básico, Pro, Enterprise'),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->label('Identificador único')
                            ->helperText('Ej: basico, pro, enterprise')
                            ->placeholder('basico'),
                        Textarea::make('description')
                            ->maxLength(500)
                            ->label('Descripción')
                            ->placeholder('Descripción del plan...')
                    ])->columns(2),

                Section::make('Límites del plan')
                    ->schema([
                        TextInput::make('max_users')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999)
                            ->default(5)
                            ->label('Máximo de usuarios')
                            ->helperText('0 = ilimitado (usa 999 para práctico)'),
                        TextInput::make('max_bank_accounts')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999)
                            ->default(50)
                            ->label('Máximo de cuentas bancarias')
                            ->helperText('0 = ilimitado (usa 999 para práctico)'),
                        TextInput::make('max_transactions_per_month')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999999)
                            ->default(500)
                            ->label('Máximo de transacciones mensuales'),
                    ])->columns(3),

                Section::make('Funcionalidades y precio')
                    ->schema([
                        TextInput::make('features')
                            ->label('Funcionalidades')
                            ->helperText('Ingresa las funcionalidades separadas por coma')
                            ->placeholder('reportes, exportar, importar, api')
                            ->formatStateUsing(fn($state) => is_array($state) ? implode(', ', $state) : $state)
                            ->afterStateHydrated(function ($component, $state) {
                                if (is_array($state)) {
                                    $component->state(implode(', ', $state));
                                }
                            })
                            ->dehydrateStateUsing(
                                fn($state) =>
                                array_map('trim', explode(',', $state ?? ''))
                            ),
                        TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->default(0)
                            ->label('Precio mensual'),
                        Select::make('currency_id')
                            ->relationship('currency', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->label('Moneda')
                            ->helperText('Moneda en la que se expresa el precio')
                            ->default(fn() => Currency::where('is_base', true)->first()?->id),
                    ])->columns(2),

                Section::make('Estado y orden')
                    ->schema([
                        Toggle::make('is_active')
                            ->default(true)
                            ->label('Plan activo'),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->label('Orden de visualización'),
                    ])->columns(2),
            ])
            ->columns(1);
    }
}
