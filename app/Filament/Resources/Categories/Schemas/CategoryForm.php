<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;;


class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la categoría')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->label('Nombre de la categoría'),
                        
                        Select::make('parent_id')
                            ->label('Categoría padre')
                            ->options(fn () => Category::whereNull('parent_id')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Dejar vacío para categoría raíz'),
                        
                        Select::make('type')
                            ->required()
                            ->options([
                                'income' => 'Ingreso',
                                'expense' => 'Egreso',
                            ])
                            ->default('expense')
                            ->label('Tipo'),
                        
                        TextInput::make('code')
                            ->maxLength(20)
                            ->label('Código contable')
                            ->helperText('Ej: 4.1.1, 5.1.2'),
                        
                        TextInput::make('icon')
                            ->maxLength(50)
                            ->default('bi-tag')
                            ->label('Icono')
                            ->helperText('Bootstrap icon (ej: bi-tag, bi-house)'),
                        
                        ColorPicker::make('color')
                            ->default('#6c757d')
                            ->label('Color'),
                        
                        Textarea::make('description')
                            ->maxLength(500)
                            ->label('Descripción')
                            ->rows(3),
                    ])->columns(2),

                Section::make('Estado y orden')
                    ->schema([
                        Toggle::make('is_system')
                            ->default(false)
                            ->label('Categoría del sistema')
                            ->helperText('Las categorías del sistema no pueden ser eliminadas'),
                        Toggle::make('is_active')
                            ->default(true)
                            ->label('Categoría activa'),
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
