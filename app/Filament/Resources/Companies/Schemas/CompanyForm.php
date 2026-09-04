<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Models\SubscriptionPlan;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la empresa')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->label('Nombre comercial')
                            ->placeholder('Ej: Hospital de Clínicas'),
                        TextInput::make('business_name')
                            ->maxLength(200)
                            ->label('Razón social')
                            ->placeholder('Ej: HOSPITAL DE CLINICAS DE CECIAMB, C.A.'),
                        TextInput::make('tax_id')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->label('RIF / NIT')
                            ->placeholder('Ej: J-30553771-2'),
                    ])->columns(2),

                Section::make('Contacto')
                    ->schema([
                        TextInput::make('email')
                            ->email()
                            ->maxLength(100)
                            ->label('Email corporativo'),
                        TextInput::make('phone')
                            ->maxLength(20)
                            ->label('Teléfono'),
                        Textarea::make('address')
                            ->maxLength(500)
                            ->label('Dirección')
                            ->rows(2),
                    ])->columns(2),

                Section::make('Personalización')
                    ->schema([
                        FileUpload::make('logo_path')
                            ->image()
                            ->directory('company-logos')
                            ->visibility('public')
                            ->label('Logo')
                            ->helperText('Sube el logo de la empresa (recomendado 500x500)'),
                        Select::make('timezone')
                            ->options([
                                'America/Caracas' => 'Caracas (UTC-4)',
                                'America/Bogota' => 'Bogotá (UTC-5)',
                                'America/Santiago' => 'Santiago (UTC-3)',
                                'America/Mexico_City' => 'Ciudad de México (UTC-6)',
                                'America/New_York' => 'Nueva York (UTC-5)',
                                'UTC' => 'UTC',
                            ])
                            ->default('America/Caracas')
                            ->label('Zona horaria'),
                    ])->columns(2),

                Section::make('Suscripción')
                    ->schema([
                        Select::make('subscription_plan_id')
                            ->relationship('subscriptionPlan', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Plan de suscripción')
                            ->helperText('El plan determina los límites de usuarios, cuentas y transacciones')
                            ->default(fn () => SubscriptionPlan::where('slug', 'free')->first()?->id),
                        DateTimePicker::make('subscription_expires_at')
                            ->label('Fecha de expiración')
                            ->helperText('Dejar vacío si no tiene fecha de expiración'),
                    ])->columns(2),

                Section::make('Estado')
                    ->schema([
                        Toggle::make('is_active')
                            ->default(true)
                            ->label('Empresa activa'),
                    ]),
            ])
            ->columns(1);
    }
}
