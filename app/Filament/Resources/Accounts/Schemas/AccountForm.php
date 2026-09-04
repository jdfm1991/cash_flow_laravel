<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la cuenta')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->label('Nombre de la cuenta')
                            ->placeholder('Ej: Consultoría, Alquiler Local, Nómina Mensual'),

                        Select::make('category_id')
                            ->required()
                            ->label('Categoría')
                            ->options(fn () => Category::where('is_active', true)
                                ->get()
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('La categoría determina el tipo (ingreso/egreso)'),

                        Textarea::make('description')
                            ->maxLength(500)
                            ->label('Descripción')
                            ->rows(3)
                            ->placeholder('Descripción de la cuenta...'),
                    ])->columns(2),

                Section::make('Integración contable')
                    ->schema([
                        TextInput::make('codigo_contable')
                            ->maxLength(20)
                            ->label('Código contable')
                            ->helperText('Código en el sistema contable externo'),
                        TextInput::make('cuenta_contable_id')
                            ->numeric()
                            ->minValue(0)
                            ->label('ID cuenta contable')
                            ->helperText('ID en el sistema contable externo'),
                    ])->columns(2),

                Section::make('Estado y orden')
                    ->schema([
                        Toggle::make('is_system')
                            ->default(false)
                            ->label('Cuenta del sistema')
                            ->helperText('Las cuentas del sistema no pueden ser eliminadas'),
                        Toggle::make('is_active')
                            ->default(true)
                            ->label('Cuenta activa'),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->label('Orden de visualización'),
                    ])->columns(3),
            ])
            ->columns(1);
    }
}
