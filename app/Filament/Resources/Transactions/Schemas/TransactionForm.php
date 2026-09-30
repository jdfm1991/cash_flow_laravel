<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Currency;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la transacción')
                    ->schema([
                        Select::make('type')
                            ->required()
                            ->options([
                                'income' => '💰 Ingreso',
                                'expense' => '💸 Egreso',
                                'transfer' => '🔄 Transferencia',
                            ])
                            ->default('income')
                            ->label('Tipo')
                            ->reactive()
                            ->afterStateUpdated(function (Set $set) {
                                $set('category_id', null);
                                $set('account_id', null);
                            }),

                        Select::make('bank_account_id')
                            ->required()
                            ->label('Cuenta bancaria')
                            ->options(function () {
                                $companyId = auth()->user()?->current_company_id;
                                
                                return BankAccount::where('company_id', $companyId)
                                    ->where('is_active', true)
                                    ->with('bank')
                                    ->get()
                                    ->mapWithKeys(fn ($item) => [
                                        $item->id => $item->alias . ' (' . ($item->bank?->name ?? 'N/A') . ')'
                                    ]);
                            })
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                Section::make('Clasificación contable')
                    ->schema([
                        Select::make('category_id')
                            ->required()
                            ->label('Categoría')
                            ->options(function (Get $get) {
                                $type = $get('type');
                                
                                if (!$type) {
                                    return [];
                                }

                                return Category::where('is_active', true)
                                    ->where('type', $type)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set) {
                                $set('account_id', null);
                            })
                            ->disabled(fn (Get $get) => !$get('type'))
                            ->helperText(fn (Get $get) => !$get('type') 
                                ? 'Selecciona primero un tipo' 
                                : 'Selecciona la categoría correspondiente'),

                        Select::make('account_id')
                            ->required()
                            ->label('Cuenta contable')
                            ->options(function (Get $get) {
                                $categoryId = $get('category_id');
                                
                                if (!$categoryId) {
                                    return [];
                                }

                                return Account::where('is_active', true)
                                    ->where('category_id', $categoryId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get) => !$get('category_id'))
                            ->helperText(fn (Get $get) => !$get('category_id') 
                                ? 'Selecciona primero una categoría' 
                                : 'Selecciona la cuenta contable'),
                    ])->columns(2),

                Section::make('Monto y moneda')
                    ->schema([
                        TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.0001)
                            ->label('Monto')
                            ->helperText('Ingresa el monto en la moneda seleccionada'),

                        Select::make('currency_id')
                            ->required()
                            ->label('Moneda')
                            ->options(fn () => Currency::where('is_active', true)
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->default(fn () => Currency::where('is_base', true)->first()?->id),
                    ])->columns(2),

                Section::make('Información adicional')
                    ->schema([
                        DatePicker::make('date')
                            ->required()
                            ->default(now())
                            ->label('Fecha')
                            ->maxDate(now()),

                        TextInput::make('reference')
                            ->maxLength(100)
                            ->label('Referencia')
                            ->placeholder('Número de factura, comprobante, etc.'),

                        Textarea::make('description')
                            ->required()
                            ->maxLength(500)
                            ->label('Descripción')
                            ->rows(3)
                            ->placeholder('Describe el concepto de la transacción...')
                            ->columnSpanFull(),

                        Select::make('payment_method')
                            ->options([
                                'bank' => 'Transferencia bancaria',
                                'cash' => 'Efectivo',
                                'transfer' => 'Transferencia entre cuentas',
                            ])
                            ->default('bank')
                            ->label('Método de pago'),
                    ])->columns(2),

                Section::make('Estado')
                    ->schema([
                        Toggle::make('is_reconciled')
                            ->label('Conciliado')
                            ->helperText('Marcar cuando la transacción coincida con el extracto bancario'),
                    ]),
            ])
            ->columns(1);
    }
}