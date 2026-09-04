<?php

namespace App\Filament\Resources\ImportSessions\Pages;

use App\Filament\Resources\ImportSessions\ImportSessionResource;
use App\Models\ExternalConnection;
use App\Models\ImportSession;
use App\Services\ExternalConnectionService;
use App\Services\ImportService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CreateImportSession extends CreateRecord
{
    protected static string $resource = ImportSessionResource::class;
    protected static bool $canCreateAnother = false;
    protected static ?string $title = 'Nueva Importación';

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    // ✅ Cambiar el texto y comportamiento del botón de Crear
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Guardar Información'); // O el texto que desees para guardar los datos
    }

    // ✅ Cambiar el texto del botón Cancelar
    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label('Cancelar'); // O el texto que desees para regresar
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // ✅ Estado inicial
        $data['status'] = 'pending';
        $data['total_rows'] = 0;
        $data['processed_rows'] = 0;
        $data['duplicated_rows'] = 0;
        $data['error_rows'] = 0;

        // ✅ Procesar archivo Excel
        if (isset($data['source']) && $data['source'] === 'excel') {
            // ✅ GUARDAR METADATOS COMPLETOS
            $data['metadata'] = [
                'bank_id' => $data['bank_id'] ?? null,                    // ✅ Guardar bank_id
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'period' => $data['period'] ?? 'all',
            ];

            // ✅ Si hay archivo, guardar ruta y nombre
            if (isset($data['file_path']) && !empty($data['file_path'])) {
                $data['file_path'] = $data['file_path'];
                $data['file_name'] = basename($data['file_path']);
            }

            // ✅ Limpiar campos que no pertenecen a la tabla
            unset($data['bank_id']);
            unset($data['bank_account_id']);
            unset($data['period']);
            unset($data['preview_before_import']);
        }

        // ✅ Procesar base de datos
        if (isset($data['source']) && $data['source'] === 'migration') {
            $data['metadata'] = [
                'connection_id' => $data['connection_id'] ?? null,
                'year' => $data['year'] ?? null,
                'month' => $data['month'] ?? null,
                'external_bank_id' => $data['external_bank_id'] ?? null,
                'external_bank_name' => $data['external_bank_name'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
            ];

            // ✅ Log para depuración
            Log::info('📝 mutateFormDataBeforeCreate - metadata', [
                'metadata' => $data['metadata'],
            ]);

            unset($data['connection_id']);
            unset($data['year']);
            unset($data['month']);
            unset($data['external_bank_id']);
            unset($data['external_bank_name']);
            unset($data['bank_account_id']);
        }

        // ✅ Limpiar otros campos
        unset($data['preview_before_import']);

        // ✅ Log final
        Log::info('📝 mutateFormDataBeforeCreate - data final', [
            'data' => $data,
        ]);

        return $data;
    }

    protected function afterCreate(): void
    {
        Log::info('🚀 afterCreate() EJECUTADO', [
            'session_id' => $this->record->id,
            'source' => $this->record->source,
            'metadata' => $this->record->metadata,
        ]);

        $session = $this->record;

        try {
            // ✅ Verificar que el archivo existe
            if ($session->source === 'excel') {
                if (!$session->file_path || !Storage::disk('public')->exists($session->file_path)) {
                    throw new \Exception("El archivo no existe en el servidor.");
                }

                // ✅ Obtener metadatos
                $metadata = $session->metadata ?? [];

                // ✅ Procesar la importación (sincrónica para pruebas)
                $importService = app(ImportService::class);
                $result = $importService->processExcelImport($session);

                // ✅ Refrescar la sesión
                $session->refresh();

                Notification::make()
                    ->success()
                    ->title('Importación completada')
                    ->body("Total: {$session->total_rows}, Procesadas: {$session->processed_rows}, Duplicadas: {$session->duplicated_rows}, Errores: {$session->error_rows}")
                    ->send();
            } elseif ($session->source === 'migration') {
                // ✅ Lógica BD Externa (NUEVA)
                $this->processDatabaseMigration($session);
            }
        } catch (\Exception $e) {
            $session->update([
                'status' => 'failed',
                'error_log' => [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
            ]);

            Notification::make()
                ->danger()
                ->title('Error en la importación')
                ->body($e->getMessage())
                ->send();

            Log::error('Error en importación', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Procesar migración desde base de datos externa
     */
    protected function processDatabaseMigration(ImportSession $session): void
    {
        Log::info('🗄️ Procesando migración desde base de datos externa', [
            'session_id' => $session->id,
            'metadata' => $session->metadata,
        ]);

        $metadata = $session->metadata ?? [];
        $connectionId = $metadata['connection_id'] ?? null;
        $year = $metadata['year'] ?? null;
        $month = $metadata['month'] ?? null;
        $externalBankId = $metadata['external_bank_id'] ?? null;
        $externalBankName = $metadata['external_bank_name'] ?? null;
        $bankAccountId = $metadata['bank_account_id'] ?? null;


        // ✅ Validar datos requeridos
        if (!$connectionId || !$year || !$month || !$externalBankName || !$bankAccountId) {
            throw new \Exception('Faltan datos de conexión (connection_id, year, month, external_bank_name, bank_account_id)');
        }

        // ✅ 1. Obtener la conexión externa
        $connection = ExternalConnection::find($connectionId);
        if (!$connection) {
            throw new \Exception("Conexión externa no encontrada: {$connectionId}");
        }

        Log::info('✅ Conexión externa encontrada', [
            'connection_id' => $connection->id,
            'name' => $connection->name,
        ]);

        // ✅ 2. Buscar el banco en nuestro sistema por nombre
        $bankId = null;
        if ($externalBankName) {
            $bank = \App\Models\Bank::where('name', 'LIKE', "%{$externalBankName}%")
                ->orWhere('name', 'LIKE', '%' . strtoupper($externalBankName) . '%')
                ->first();

            if ($bank) {
                $bankId = $bank->id;
                Log::info('✅ Banco encontrado en el sistema', [
                    'external_name' => $externalBankName,
                    'system_bank_id' => $bankId,
                    'system_bank_name' => $bank->name,
                ]);
            } else {
                Log::warning('⚠️ Banco no encontrado en el sistema', [
                    'external_bank_name' => $externalBankName,
                ]);
            }
        }

        // ✅ 3. Probar conexión antes de proceder
        $connectionService = app(ExternalConnectionService::class);

        // ✅ Obtener datos de conexión incluyendo la contraseña
        $connectionData = $connection->toArray();
        $connectionData['password'] = $connection->getDecryptedPassword();

        $testResult = $connectionService->testConnection($connectionData);

        if (!$testResult['success']) {
            throw new \Exception("Error de conexión a base de datos externa: {$testResult['message']}");
        }

        Log::info('✅ Conexión exitosa a base de datos externa');

        // ✅ 4. Obtener datos (NO filtrar por banco externo, obtener TODOS)
        $importService = app(ImportService::class);
        $externalDb = $connectionService->getPdoConnection($connection);
        $data = $importService->fetchExternalData(
            $externalDb,
            $connection,
            $year,
            $month,
            'all',
            $externalBankId
        );

        // ✅ 5. Crear log de migración
        $log = $importService->startMigration(
            companyId: $session->company_id,
            userId: $session->user_id,
            connectionId: $connection->id,
            type: 'all',
            year: $year,
            month: $month,
        );

        // ✅ 6. Procesar datos y guardar en imported_transactions (con bank_id del sistema)
        $result = $importService->processExternalData(
            $data,
            $log,
            $bankId,
            $bankAccountId,
            $session->id  // ✅ PASAR EL ID DE SESIÓN
        );

        // ✅ 7. Actualizar la sesión
        $session->update([
            'total_rows' => $result['total'],
            'processed_rows' => $result['imported'],
            'duplicated_rows' => $result['duplicated'],
            'error_rows' => $result['failed'],
            'status' => 'completed',
            'completed_at' => now(),
            'metadata' => array_merge($metadata, [
                'migration_log_id' => $log->id,
                'system_bank_id' => $bankId,
            ]),
        ]);

        Notification::make()
            ->success()
            ->title('Migración completada')
            ->body("Total: {$result['total']}, Importados: {$result['imported']}, Duplicados: {$result['duplicated']}, Fallidos: {$result['failed']}")
            ->send();

        Log::info('✅ Migración desde BD externa completada', [
            'session_id' => $session->id,
            'log_id' => $log->id,
            'system_bank_id' => $bankId,
        ]);
    }
}
