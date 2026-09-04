<?php

namespace App\Filament\Resources\ExternalConnections\Schemas;

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;

class ExternalConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la conexión')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->label('Nombre descriptivo')
                            ->placeholder('Ej: Sparrow Administrativo - HCC')
                            ->helperText('Nombre para identificar esta conexión'),

                        Select::make('company_id')
                            ->required()
                            ->label('Empresa')
                            ->relationship('company', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->user()?->current_company_id),

                        Select::make('type')
                            ->options([
                                'migration' => 'Migración',
                                'replication' => 'Replicación',
                                'integration' => 'Integración',
                            ])
                            ->default('migration')
                            ->required()
                            ->label('Tipo de conexión'),
                    ])->columns(2),

                Section::make('Datos de la base de datos')
                    ->schema([
                        TextInput::make('host')
                            ->required()
                            ->maxLength(255)
                            ->label('Host')
                            ->placeholder('192.168.1.3'),

                        TextInput::make('port')
                            ->numeric()
                            ->default(3306)
                            ->minValue(1)
                            ->maxValue(65535)
                            ->label('Puerto'),

                        TextInput::make('db_name')
                            ->required()
                            ->maxLength(100)
                            ->label('Base de datos')
                            ->placeholder('sparrow_siadcli'),

                        TextInput::make('username')
                            ->required()
                            ->maxLength(100)
                            ->label('Usuario'),

                        TextInput::make('password')
                            ->password()
                            ->required(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord)
                            ->maxLength(255)
                            ->label('Contraseña')
                            ->helperText('La contraseña se encripta al guardar'),
                    ])->columns(2),

                Section::make('Configuración de la tabla')
                    ->schema([
                        TextInput::make('table_name')
                            ->required()
                            ->maxLength(100)
                            ->label('Tabla origen')
                            ->placeholder('adm_bancos_operaciones'),

                        KeyValue::make('field_mapping')
                            ->label('Mapeo de campos')
                            ->keyLabel('Campo origen')
                            ->valueLabel('Campo destino')
                            ->addActionLabel('Agregar mapeo')
                            ->helperText('Ej: date → fecha_operacion, income → ingresos')
                            ->default([
                                'date' => 'fecha_operacion',
                                'income' => 'ingresos',
                                'expense' => 'egresos',
                                'reference' => 'numero_documento',
                                'description' => 'conceptos',
                            ]),

                        Textarea::make('query_template')
                            ->label('SQL personalizado (opcional)')
                            ->rows(3)
                            ->helperText('Si se especifica, reemplaza la consulta por defecto. Usa :year, :month, :type como parámetros.')
                            ->placeholder('SELECT * FROM tabla WHERE YEAR(date) = :year AND MONTH(date) = :month AND type = :type'),
                    ]),

                Section::make('Estado')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Conexión activa')
                            ->default(true)
                            ->helperText('Desactivar para evitar migraciones desde esta conexión'),
                    ]),

                // ✅ Botón para probar conexión (se muestra en edición)
                Section::make('Prueba de conexión')
                    ->schema([
                        Actions::make([
                            Action::make('testConnection')
                                ->label('🔌 Probar conexión')
                                ->color('primary')
                                ->icon('heroicon-o-wifi')
                                ->action(function ($livewire, $get) {
                                    try {
                                        $data = [
                                            'host' => $get('host'),
                                            'port' => $get('port'),
                                            'db_name' => $get('db_name'),
                                            'username' => $get('username'),
                                            'password' => $get('password'),
                                        ];

                                        $service = app(\App\Services\ExternalConnectionService::class);
                                        $result = $service->testConnection($data);

                                        if ($result['success']) {
                                            Notification::make()
                                                ->success()
                                                ->title('Conexión exitosa')
                                                ->body('La conexión a la base de datos externa funciona correctamente.')
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->danger()
                                                ->title('Error de conexión')
                                                ->body($result['message'])
                                                ->send();
                                        }
                                    } catch (\Exception $e) {
                                        Notification::make()
                                            ->danger()
                                            ->title('Error')
                                            ->body($e->getMessage())
                                            ->send();
                                    }
                                }),
                        ]),
                    ])
                    ->visible(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
            ]);
    }
}