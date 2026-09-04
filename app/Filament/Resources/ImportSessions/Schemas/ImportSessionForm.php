<?php

namespace App\Filament\Resources\ImportSessions\Schemas;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\ExternalConnection;
use App\Services\ExternalConnectionService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;

class ImportSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // ✅ CAMPO OCULTO PARA USER_ID
                Hidden::make('user_id')
                    ->default(fn() => filament()->auth()->user()?->id ?? auth()->id())
                    ->required(),

                // ✅ EMPRESA - Selector obligatorio
                Section::make('Empresa')
                    ->schema([
                        Select::make('company_id')
                            ->label('Empresa')
                            ->required()
                            ->options(fn() => self::getCompanyOptions())
                            ->default(fn() => auth()->user()?->current_company_id)
                            ->searchable()
                            ->preload()
                            ->helperText('Selecciona la empresa donde se importarán los datos')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('bank_account_id', null);
                                $set('connection_id', null);
                            }),
                    ])
                    ->columns(1)
                    ->columnSpan(4),

                Section::make('Seleccionar fuente de importación')
                    ->schema([
                        Radio::make('source')
                            ->label('Origen de los datos')
                            ->options([
                                'excel' => '📊 Archivo Excel / CSV',
                                'migration' => '🗄️ Base de datos externa',
                            ])
                            ->default('excel')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state === 'excel') {
                                    $set('connection_id', null);
                                    $set('year', null);
                                    $set('month', null);
                                    $set('external_bank_id', null);
                                } else {
                                    $set('file', null);
                                    $set('bank_id', null);
                                    $set('bank_account_id', null);
                                }
                            }),
                    ])
                    ->columns(1)
                    ->columnSpan(4),

                // ============================================================
                // SECCIÓN EXCEL
                // ============================================================
                Section::make('Datos del archivo')
                    ->schema([
                        Select::make('bank_id')
                            ->label('Banco')
                            ->required(fn($get) => $get('source') === 'excel')
                            ->options(fn() => Bank::active()
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Selecciona el banco del extracto')
                            ->visible(fn($get) => $get('source') === 'excel')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('bank_account_id', null);
                            }),

                        Select::make('bank_account_id')
                            ->label('Cuenta bancaria destino')
                            ->required(fn($get) => $get('source') === 'excel')
                            ->options(fn($get) => self::getBankAccountOptionsForExcel($get('company_id'), $get('bank_id')))
                            ->searchable()
                            ->preload()
                            ->helperText('Cuenta donde se registrarán las transacciones')
                            ->visible(fn($get) => $get('source') === 'excel'),

                        FileUpload::make('file_path')  // ✅ CAMBIAR: usar 'file_path' en lugar de 'file'
                            ->label('Archivo Excel / CSV')
                            ->required(fn($get) => $get('source') === 'excel')
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                                'application/csv',
                            ])
                            ->maxSize(5120)
                            ->directory('imports')
                            ->disk('public')
                            ->visibility('public')
                            ->storeFileNamesIn('file_name')  // ✅ Guarda el nombre original en 'file_name'
                            ->columnSpanFull()
                            ->helperText('Formatos aceptados: XLSX, XLS, CSV (máx. 5MB)')
                            ->visible(fn($get) => $get('source') === 'excel'),

                        Select::make('period')
                            ->label('Período')
                            ->options([
                                'all' => 'Todos los movimientos',
                                'current' => 'Mes actual',
                                'previous' => 'Mes anterior',
                                'custom' => 'Personalizado',
                            ])
                            ->default('all')
                            ->visible(fn($get) => $get('source') === 'excel'),
                    ])
                    ->visible(fn($get) => $get('source') === 'excel')
                    ->columns(2)
                    ->columnSpan(10),

                // ============================================================
                // SECCIÓN BASE DE DATOS EXTERNA
                // ============================================================
                Section::make('Conexión a base de datos externa')
                    ->schema([
                        Select::make('connection_id')
                            ->label('Conexión externa')
                            ->required(fn($get) => $get('source') === 'migration')
                            ->options(fn($get) => self::getConnectionOptions($get('company_id')))
                            ->searchable()
                            ->preload()
                            ->helperText('Selecciona la conexión configurada')
                            ->visible(fn($get) => $get('source') === 'migration')
                            ->live()  // ✅ Mantener live para conexión
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('year', null);
                                $set('month', null);
                                $set('external_bank_id', null);
                                $set('external_bank_name', null);
                                $set('bank_account_id', null);
                            }),

                        Select::make('year')
                            ->label('Año')
                            ->required(fn($get) => $get('source') === 'migration')
                            ->options(fn($get) => self::getYearsOptions($get('connection_id')))
                            ->searchable()
                            ->preload()
                            ->visible(fn($get) => $get('source') === 'migration')
                            ->reactive()  // ✅ CAMBIAR: reactive en lugar de live
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('month', null);
                                $set('external_bank_id', null);
                                $set('external_bank_name', null);
                                $set('bank_account_id', null);
                            }),

                        Select::make('month')
                            ->label('Mes')
                            ->required(fn($get) => $get('source') === 'migration')
                            ->options(fn($get) => self::getMonthsOptions($get('connection_id'), $get('year')))
                            ->searchable()
                            ->preload()
                            ->visible(fn($get) => $get('source') === 'migration')
                            ->reactive()  // ✅ CAMBIAR: reactive en lugar de live
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('external_bank_id', null);
                                $set('external_bank_name', null);
                                $set('bank_account_id', null);
                            }),

                        Hidden::make('external_bank_name')
                            ->default(null),

                        Select::make('external_bank_id')
                            ->label('Banco externo')
                            ->required(fn($get) => $get('source') === 'migration')
                            ->options(fn($get) => self::getExternalBanksOptions(
                                $get('connection_id'),
                                $get('year'),
                                $get('month')
                            ))
                            ->searchable()
                            ->preload()
                            ->visible(fn($get) => $get('source') === 'migration')
                            ->helperText('Banco en el sistema externo')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                // ✅ Guardar también el nombre del banco
                                $options = self::getExternalBanksOptions(
                                    $get('connection_id'),
                                    $get('year'),
                                    $get('month')
                                );

                                // ✅ Guardar el nombre del banco seleccionado
                                if ($state && isset($options[$state])) {
                                    $set('external_bank_name', $options[$state]);
                                    // ✅ Al seleccionar banco, filtrar cuentas bancarias
                                    $set('bank_account_id', null);
                                    Log::info('🔍 Banco externo seleccionado', [
                                        'external_bank_id' => $state,
                                        'external_bank_name' => $options[$state],
                                    ]);
                                } else {
                                    $set('external_bank_name', null);
                                }
                            }),

                        Select::make('bank_account_id')
                            ->label('Cuenta bancaria destino')
                            ->required(fn($get) => $get('source') === 'migration')
                            ->options(function ($get) {
                                $options = self::getBankAccountOptionsForDatabase(
                                    $get('company_id'),
                                    $get('external_bank_name')
                                );
                                Log::info('🔄 bank_account_id options', [
                                    'company_id' => $get('company_id'),
                                    'external_bank_name' => $get('external_bank_name'),
                                    'options' => $options,
                                ]);
                                return $options;
                            })
                            ->searchable()
                            ->preload()
                            ->visible(fn($get) => $get('source') === 'migration')
                            ->helperText('Selecciona la cuenta bancaria donde se registrarán los movimientos')
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                Log::info('🔍 Cuenta bancaria seleccionada', [
                                    'bank_account_id' => $state,
                                ]);
                            }),
                    ])
                    ->visible(fn($get) => $get('source') === 'migration')
                    ->columns(2)
                    ->columnSpan(10),
            ]);
    }

    // ================================================================
    // FUNCIONES DE AYUDA
    // ================================================================

    protected static function getCompanyOptions(): array
    {
        $user = filament()->auth()->user() ?? auth()->user();

        if (!$user) {
            return [];
        }

        if ($user->hasRole('super_admin')) {
            return Company::where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
        }

        return $user->companies()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Obtener opciones de cuentas bancarias para BD (por bank_name)
     */
    protected static function getBankAccountOptionsno(?int $companyId, ?string $bankName = null): array
    {
        if (!$companyId) {
            return [];
        }

        $query = BankAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->with('bank');

        // ✅ Filtrar por nombre del banco si se proporciona
        if ($bankName) {
            $query->whereHas('bank', function ($q) use ($bankName) {
                // ✅ Buscar coincidencia exacta o parcial
                $q->where('name', 'LIKE', "%{$bankName}%")
                    ->orWhere('name', 'LIKE', '%' . strtoupper($bankName) . '%');
            });
        }

        $result = $query->get()
            ->mapWithKeys(fn($item) => [
                $item->id => $item->alias . ' (' . ($item->bank?->name ?? 'N/A') . ') - ' . $item->account_number
            ])
            ->toArray();

        // ✅ Log para depuración
        Log::info('🔍 getBankAccountOptions', [
            'company_id' => $companyId,
            'bank_name' => $bankName,
            'results' => $result,
        ]);

        return $result;
    }

    /**
     * Obtener opciones de cuentas bancarias para Excel (por bank_id)
     */
    protected static function getBankAccountOptionsForExcel(?int $companyId, ?int $bankId): array
    {
        if (!$companyId) {
            return [];
        }

        $query = BankAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->with('bank');

        if ($bankId) {
            $query->where('bank_id', $bankId);
        }

        return $query->get()
            ->mapWithKeys(fn($item) => [
                $item->id => $item->alias . ' (' . ($item->bank?->name ?? 'N/A') . ') - ' . $item->account_number
            ])
            ->toArray();
    }

    /**
     * Obtener opciones de cuentas bancarias para BD Externa (por nombre de banco)
     */
    protected static function getBankAccountOptionsForDatabase(?int $companyId, ?string $bankName): array
    {
        if (!$companyId) {
            return [];
        }

        $query = BankAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->with('bank');

        if ($bankName) {
            $query->whereHas('bank', function ($q) use ($bankName) {
                $q->where('name', 'LIKE', "%{$bankName}%")
                    ->orWhere('name', 'LIKE', '%' . strtoupper($bankName) . '%');
            });
        }

        return $query->get()
            ->mapWithKeys(fn($item) => [
                $item->id => $item->alias . ' (' . ($item->bank?->name ?? 'N/A') . ') - ' . $item->account_number
            ])
            ->toArray();
    }

    protected static function getConnectionOptions(?int $companyId): array
    {
        if (!$companyId) {
            return [];
        }

        return ExternalConnection::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', 'migration')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function getYearsOptions(?int $connectionId): array
    {
        if (!$connectionId) {
            return [];
        }

        try {
            $connection = ExternalConnection::find($connectionId);
            if (!$connection) {
                return [];
            }

            $service = app(ExternalConnectionService::class);
            $years = $service->getAvailableYears($connection);

            // ✅ Verificar que $years no esté vacío
            if (empty($years)) {
                Log::warning('No se encontraron años para la conexión', [
                    'connection_id' => $connectionId,
                ]);
                return [];
            }

            return array_combine($years, $years);
        } catch (\Exception $e) {
            Log::error('Error obteniendo años', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected static function getMonthsOptions(?int $connectionId, ?int $year): array
    {
        if (!$connectionId || !$year) {
            return [];
        }

        try {
            $connection = ExternalConnection::find($connectionId);
            if (!$connection) {
                return [];
            }

            $service = app(ExternalConnectionService::class);
            $months = $service->getAvailableMonths($connection, $year);

            Log::info('🔍 getMonthsOptions', [
                'connection_id' => $connectionId,
                'year' => $year,
                'months' => $months,
            ]);

            if (empty($months)) {
                Log::warning('No se encontraron meses para la conexión y año', [
                    'connection_id' => $connectionId,
                    'year' => $year,
                ]);
                return [];
            }

            $monthNames = [
                1 => 'Enero',
                2 => 'Febrero',
                3 => 'Marzo',
                4 => 'Abril',
                5 => 'Mayo',
                6 => 'Junio',
                7 => 'Julio',
                8 => 'Agosto',
                9 => 'Septiembre',
                10 => 'Octubre',
                11 => 'Noviembre',
                12 => 'Diciembre',
            ];

            $result = [];
            foreach ($months as $month) {
                $result[$month] = $monthNames[$month] ?? $month;
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Error obteniendo meses', [
                'connection_id' => $connectionId,
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected static function getExternalBanksOptions(?int $connectionId, ?int $year, ?int $month): array
    {
        if (!$connectionId || !$year || !$month) {
            return [];
        }

        try {
            $connection = ExternalConnection::find($connectionId);
            if (!$connection) {
                return [];
            }

            $service = app(ExternalConnectionService::class);
            $banks = $service->getAvailableBanks($connection, $year, $month);

            $result = [];
            foreach ($banks as $bank) {
                $result[$bank['bank_id']] = $bank['bank_name'] ?? 'Banco #' . $bank['bank_id'];
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }
}
