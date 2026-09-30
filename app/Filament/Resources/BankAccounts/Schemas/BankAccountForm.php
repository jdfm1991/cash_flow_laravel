<?php

namespace App\Filament\Resources\BankAccounts\Schemas;

use App\Models\Bank;
use App\Models\Currency;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la cuenta')
                    ->schema([
                        // ✅ Eliminado: company_id (se asigna automáticamente desde el contexto)

                        Select::make('bank_id')
                            ->required()
                            ->label('Banco')
                            ->options(fn () => Bank::where('is_active', true)
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),

                        Select::make('currency_id')
                            ->required()
                            ->label('Moneda')
                            ->options(fn () => Currency::where('is_active', true)
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->default(fn () => Currency::where('is_base', true)->first()?->id),
                    ])->columns(2),

                Section::make('Detalles de la cuenta')
                    ->schema([
                        TextInput::make('account_number')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->label('Número de cuenta')
                            ->placeholder('Ej: 01081028785478962450'),

                        TextInput::make('alias')
                            ->required()
                            ->maxLength(100)
                            ->label('Alias o nombre descriptivo')
                            ->placeholder('Ej: Cuenta Principal, Cuenta de Nómina'),

                        Select::make('account_type')
                            ->required()
                            ->options([
                                'corriente' => 'Cuenta Corriente',
                                'ahorros' => 'Cuenta de Ahorros',
                                'nomina' => 'Cuenta de Nómina',
                                'inversion' => 'Cuenta de Inversión',
                                'caja_chica' => 'Caja Chica',
                                'efectivo' => 'Efectivo',
                                'tarjeta_credito' => 'Tarjeta de Crédito',
                                'virtual' => 'Cuenta Virtual',
                            ])
                            ->default('corriente')
                            ->label('Tipo de cuenta'),

                        TextInput::make('account_holder')
                            ->maxLength(100)
                            ->label('Titular de la cuenta')
                            ->helperText('Si es diferente a la empresa'),
                    ])->columns(2),

                Section::make('Saldo inicial')
                    ->schema([
                        Fieldset::make('Saldo')
                            ->schema([
                                TextInput::make('opening_balance')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.0001)
                                    ->default(0)
                                    ->label('Saldo inicial')
                                    ->helperText('Saldo al momento de crear la cuenta'),
                                DatePicker::make('opened_at')
                                    ->default(now())
                                    ->label('Fecha de apertura')
                                    ->helperText('Fecha en que se abrió la cuenta'),
                            ])->columns(2),
                    ]),

                Section::make('Notas y estado')
                    ->schema([
                        Textarea::make('notes')
                            ->maxLength(500)
                            ->label('Notas adicionales')
                            ->rows(3),
                        Toggle::make('is_active')
                            ->default(true)
                            ->label('Cuenta activa'),
                        Toggle::make('is_default')
                            ->label('Cuenta predeterminada')
                            ->helperText('Marcar como cuenta por defecto para la empresa'),
                    ]),
            ])
            ->columns(1);
    }
}