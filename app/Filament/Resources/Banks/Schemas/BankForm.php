<?php

namespace App\Filament\Resources\Banks\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

class BankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->label('Nombre del banco'),
                        TextInput::make('code')
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->label('Código bancario')
                            ->helperText('Ej: 0182 para Provincial, 0102 para Banco de Venezuela'),
                        Select::make('country_code')
                            ->options([
                                'VE' => 'Venezuela',
                                'CO' => 'Colombia',
                                'US' => 'Estados Unidos',
                                'MX' => 'México',
                                'AR' => 'Argentina',
                                'CL' => 'Chile',
                                'PE' => 'Perú',
                                'ES' => 'España',
                            ])
                            ->searchable()
                            ->label('País'),
                        TextInput::make('website')
                            ->url()
                            ->maxLength(255)
                            ->label('Sitio web'),
                        TextInput::make('phone')
                            ->maxLength(20)
                            ->label('Teléfono'),
                        Toggle::make('is_active')
                            ->default(true)
                            ->label('Activo'),
            ]);
    }
}
