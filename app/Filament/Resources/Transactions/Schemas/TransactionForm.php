<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
                                'income' => 'Ingreso',
                                'expense' => 'Egreso',
                                'transfer' => 'Transferencia',
                            ])
                            ->default('income')
                            ->label('Tipo')
                            ->reactive()
                            ->afterStateUpdated(fn ($set) => $set('account_id', null)),

                        Select::make('company_id')
                            ->required()
                            ->label('Empresa')
                            ->options(fn () => Company::where('is_active', true)
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->user()?->current_company_id),

                        Select::make('bank_account_id')
                            ->required()
                            ->label('Cuenta bancaria')
                            ->options(fn () => BankAccount::where('is_active', true)
                                ->with('bank')
                                ->get()
                                ->mapWithKeys(fn ($item) => [
                                    $item->id => $item->alias . ' (' . ($item->bank?->name ?? 'N/A') . ')'
                                ]))
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                Section::make('Clasificación contable')
                    ->schema([
                        Select::make('account_id')
                            ->required()
                            ->label('Cuenta contable')
                            ->options(fn ($get) => Account::where('is_active', true)
                                ->when($get('type'), fn ($query, $type) => 
                                    $query->whereHas('category', fn ($q) => $q->where('type', $type))
                                )
                                ->with('category')
                                ->get()
                                ->mapWithKeys(fn ($item) => [
                                    $item->id => $item->name . ' (' . ($item->category?->name ?? 'N/A') . ')'
                                ]))
                            ->searchable()
                            ->preload(),

                        Select::make('category_id')
                            ->required()
                            ->label('Categoría')
                            ->options(fn ($get) => Category::where('is_active', true)
                                ->when($get('type'), fn ($query, $type) => 
                                    $query->where('type', $type)
                                )
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                Section::make('Monto y moneda')
                    ->schema([
                        TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.0001)
                            ->label('Monto'),

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
                            ->placeholder('Describe el concepto de la transacción...'),

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
