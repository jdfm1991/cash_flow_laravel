<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\ImportedTransaction;
use App\Models\ImportSession;
use App\Models\MigrationLog;
use App\Models\MigrationMapping;
use App\Models\ExternalConnection;
use App\DTOs\TransactionData;
use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Enums\TransactionType;
use App\Exceptions\DuplicateTransactionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportService
{
    public function __construct(
        protected TransactionService $transactionService,
        protected CurrencyService $currencyService,
        protected BalanceService $balanceService,
    ) {}

    /**
     * Iniciar una sesión de importación desde Excel
     */
    public function startExcelImport(
        int $companyId,
        int $userId,
        string $filePath,
        string $bankCode,
        int $bankAccountId,
        array $options = []
    ): ImportSession {
        // 1. Crear sesión de importación
        $session = ImportSession::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'source' => ImportSource::EXCEL->value,
            'file_name' => basename($filePath),
            'file_path' => $filePath,
            'total_rows' => 0,
            'processed_rows' => 0,
            'duplicated_rows' => 0,
            'error_rows' => 0,
            'status' => ImportStatus::PENDING->value,
            'metadata' => [
                'bank_code' => $bankCode,
                'bank_account_id' => $bankAccountId,
                'options' => $options,
            ],
        ]);

        // 2. Programar procesamiento en background (opcional)
        // Por ahora, procesamos sincrónicamente

        return $session;
    }

    /**
     * Procesar importación desde Excel
     */
    public function processExcelImport(ImportSession $session): ImportSession
    {
        // ✅ Log de inicio
        Log::info('🔄 PROCESANDO IMPORTACIÓN EXCEL', [
            'session_id' => $session->id,
            'file_path' => $session->file_path,
        ]);

        $session->update(['status' => 'processing', 'started_at' => now()]);

        try {
            // ✅ Log de configuración del banco
            $bankConfig = $this->getBankConfig(
                $session->metadata['bank_id'] ?? 0,
                $session->metadata['bank_account_id'] ?? 0
            );
            Log::info('📋 CONFIGURACIÓN DEL BANCO', ['bankConfig' => $bankConfig]);

            // ✅ Verificar que el archivo existe
            if (!Storage::disk('public')->exists($session->file_path)) {
                throw new \Exception("El archivo no existe: {$session->file_path}");
            }
            Log::info('📂 ARCHIVO EXISTE', ['file_path' => $session->file_path]);

            // ✅ Crear el import
            $import = new \App\Imports\BankStatementImport(
                $bankConfig,
                $session->company_id,
                $session->metadata['bank_account_id'] ?? 0,
                $session->user_id,
                $session->id,
            );
            Log::info('✅ IMPORT CREADO');

            // ✅ Procesar el archivo
            $fullPath = Storage::disk('public')->path($session->file_path);
            Log::info('📂 RUTA COMPLETA', ['fullPath' => $fullPath]);

            \Maatwebsite\Excel\Facades\Excel::import(
                new \App\Imports\BankStatementImport(
                    $bankConfig,
                    $session->company_id,
                    $session->metadata['bank_account_id'] ?? 0,
                    $session->user_id,
                    $session->id,
                ),
                $fullPath
            );
            Log::info('📊 EXCEL IMPORTADO');

            // ✅ Obtener estadísticas
            $stats = $import->getStats();
            Log::info('📊 ESTADÍSTICAS', ['stats' => $stats]);

            // ✅ Actualizar la sesión
            $session->update([
                'total_rows' => $stats['total'],
                'processed_rows' => $stats['imported'],
                'duplicated_rows' => $stats['duplicated'],
                'error_rows' => $stats['errors'],
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            Log::info('✅ IMPORTACIÓN COMPLETADA', ['session_id' => $session->id]);
        } catch (\Exception $e) {
            Log::error('❌ ERROR EN IMPORTACIÓN', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $session->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_log' => [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
            ]);
        }

        return $session;
    }


    /**
     * Iniciar migración desde sistema externo
     */
    public function startMigration(
        int $companyId,
        int $userId,
        int $connectionId,
        string $type,
        int $year,
        int $month,
    ): MigrationLog {
        // 1. Crear log de migración
        $log = MigrationLog::create([
            'company_id' => $companyId,
            'connection_id' => $connectionId,
            'migration_type' => $type, // 'income', 'expense', 'all'
            'year' => $year,
            'month' => $month,
            'total_records' => 0,
            'imported_records' => 0,
            'duplicated_records' => 0,
            'failed_records' => 0,
            'status' => 'pending',
            'created_by' => $userId,
        ]);

        return $log;
    }


    /**
     * Procesar datos externos y guardar en imported_transactions
     */
    public function processExternalData(
        array $data,
        MigrationLog $log,
        ?int $systemBankId = null,
        ?int $bankAccountId = null,
        ?int $sessionId = null
    ): array {

        $stats = [
            'total' => count($data),
            'imported' => 0,
            'duplicated' => 0,
            'failed' => 0,
        ];

        // ✅ Usar sessionId si se proporciona, si no, usar log->id como fallback
        $importSessionId = $sessionId ?? $log->id;
        $currency = $this->currencyService->getBaseCurrency();

        foreach ($data as $row) {
            try {
                $type = $this->determineType($row, $log->migration_type);
                $hash = $this->generateImportHash($row, $log->company_id);

                $existing = ImportedTransaction::where('company_id', $log->company_id)
                    ->where('hash', $hash)
                    ->first();

                if ($existing) {
                    $stats['duplicated']++;
                    continue;
                }

                // ✅ Guardar el bank_id del sistema (no el externo)
                ImportedTransaction::create([
                    'company_id' => $log->company_id,
                    'bank_id' => $systemBankId,  // ✅ ID del banco en nuestro sistema
                    'bank_name' => $row['bank_name'],  // Solo para referencia
                    'bank_account_id' => $bankAccountId,
                    'transaction_date' => $row['date'] ?? now()->toDateString(),
                    'reference' => $row['reference'] ?? null,
                    'description' => $row['description'] ?? 'Migración desde BD externa',
                    'amount' => abs($row['amount'] ?? 0),
                    'transaction_type' => $type,
                    'amount_converted' => $row['amount_converted'] ?? null,
                    'currency_id' => $currency->id,
                    'exchange_rate' => $row['exchange_rate'] ?? null,
                    'is_processed' => false,
                    'import_session_id' => (string) $importSessionId,
                    'hash' => $hash,
                ]);

                $stats['imported']++;
                app(AuditService::class)->logImport('Migration', [
                    'total' => $stats['total'],
                    'imported' => $stats['imported'],
                    'duplicated' => $stats['duplicated'],
                    'errors' => $stats['errors'],
                ]);
            } catch (\Exception $e) {
                $stats['failed']++;
                Log::error('Error procesando fila externa', [
                    'row' => $row,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Crear transacción importada
     */
    protected function createImportedTransaction(
        array $row,
        MigrationLog $log,
        ?array $mapping
    ): ImportedTransaction {
        // 1. Determinar tipo de transacción
        $type = $this->determineType($row, $log->migration_type);

        // 2. Generar hash
        $hash = $this->generateImportHash($row, $log->company_id);

        // 3. Verificar duplicado
        $existing = ImportedTransaction::where('hash', $hash)->first();
        if ($existing) {
            throw new DuplicateTransactionException($hash, $existing->id);
        }

        // 4. Crear registro importado
        $imported = ImportedTransaction::create([
            'company_id' => $log->company_id,
            'bank_id' => $mapping['bank_id'] ?? null,
            'bank_name' => $row['bank_name'] ?? null,
            'bank_account_id' => $mapping['bank_account_id'] ?? null,
            'transaction_date' => $row['date'] ?? null,
            'reference' => $row['reference'] ?? null,
            'description' => $row['description'] ?? null,
            'amount' => $row['amount'] ?? 0,
            'transaction_type' => $type,
            'amount_converted' => $row['amount_converted'] ?? null,
            'original_currency' => $row['currency'] ?? null,
            'exchange_rate' => $row['exchange_rate'] ?? null,
            'is_processed' => false,
            'mapped_account_id' => $mapping['account_id'] ?? null,
            'mapped_category' => $mapping['category'] ?? null,
            'import_session_id' => $log->id,
            'hash' => $hash,
        ]);

        return $imported;
    }

    /**
     * Procesar transacciones importadas (convertir en transacciones reales)
     */
    public function processImportedTransactions(
        int $companyId,
        string $sessionId
    ): array {
        $results = [
            'processed' => 0,
            'errors' => 0,
            'transactions' => [],
        ];

        // 1. Obtener transacciones importadas no procesadas
        $imported = ImportedTransaction::where('company_id', $companyId)
            ->where('import_session_id', $sessionId)
            ->where('is_processed', false)
            ->get();

        foreach ($imported as $item) {
            try {
                // 2. Crear transacción real
                $transaction = $this->convertImportedToTransaction($item);
                $results['processed']++;
                $results['transactions'][] = $transaction;

                // 3. Marcar como procesada
                $item->markAsProcessed($transaction->account_id);
            } catch (\Exception $e) {
                $results['errors']++;
                Log::error("Error procesando transacción importada", [
                    'imported_id' => $item->id,
                    'error' => $e->getMessage(),
                ]);

                // Registrar error en el item
                $item->update([
                    'error_log' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Convertir transacción importada a transacción real
     */
    protected function convertImportedToTransaction(ImportedTransaction $item): Transaction
    {
        // 1. Buscar cuenta contable
        $accountId = $item->mapped_account_id;
        if (!$accountId) {
            $account = $this->findOrCreateAccount($item);
            $accountId = $account->id;
        }

        // 2. Buscar categoría
        $categoryId = $this->findCategory($item);

        // 3. Crear TransactionData
        $data = new TransactionData(
            companyId: $item->company_id,
            userId: $item->user_id ?? 1, // Usuario por defecto si no tiene
            type: $item->transaction_type,
            amount: $item->amount,
            currencyId: $this->getCurrencyId($item),
            date: $item->transaction_date,
            description: $item->description ?? 'Importado desde sistema externo',
            accountId: $accountId,
            categoryId: $categoryId,
            bankAccountId: $item->bank_account_id ?? 0,
            reference: $item->reference,
            paymentMethod: 'bank',
        );

        // 4. Crear transacción
        return $this->transactionService->create($data);
    }

    /**
     * Obtener configuración del banco
     */
    protected function getBankConfig(string $bankCode, int $bankAccountId): array
    {
        // ✅ Cargar configuración directamente
        $config = config('import.banks', []);

        // ✅ Log para debugging
        Log::info('🔍 getBankConfig - Cargando configuración', [
            'bankCode' => $bankCode,
            'bankAccountId' => $bankAccountId,
            'available_configs' => array_keys($config),
        ]);

        // ✅ Si bankCode es numérico, buscar por ID
        if (is_numeric($bankCode) && isset($config[$bankCode])) {
            Log::info('✅ Configuración encontrada por ID', ['bank_id' => $bankCode]);
            return $config[$bankCode];
        }

        // ✅ Por código (string)
        foreach ($config as $id => $bank) {
            if (($bank['code'] ?? '') === $bankCode) {
                Log::info('✅ Configuración encontrada por código', ['code' => $bankCode]);
                return $bank;
            }
        }

        // ✅ Por ID de cuenta
        if ($bankAccountId) {
            $bankAccount = BankAccount::find($bankAccountId);
            if ($bankAccount && $bankAccount->bank) {
                Log::info('🔍 Buscando por cuenta bancaria', ['bank_id' => $bankAccount->bank_id]);
                return $this->getBankConfig((string) $bankAccount->bank_id, 0);
            }
        }

        Log::warning('⚠️ Configuración genérica usada', [
            'bankCode' => $bankCode,
            'bankAccountId' => $bankAccountId,
        ]);

        return [
            'name' => 'Genérico',
            'code' => 'generic',
            'patterns' => [
                'start_row' => 1,
                'date_formats' => ['dd/mm/yyyy', 'yyyy-mm-dd'],
            ],
        ];
    }

    /**
     * Conectar a base de datos externa
     */
    protected function connectExternalDB(ExternalConnection $connection): \PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $connection->host,
            $connection->port ?? 3306,
            $connection->db_name
        );

        // ✅ Obtener la contraseña desencriptada
        $password = $connection->getDecryptedPassword();

        return new \PDO(
            $dsn,
            $connection->username,
            $password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }

    /**
     * Obtener datos de sistema externo
     */
    public function fetchExternalData(
        \PDO $db,
        ExternalConnection $connection,
        int $year,
        int $month,
        string $type,
        ?int $externalBankId = null
    ): array {
        // ✅ Obtener field_mapping
        $fieldMapping = $connection->field_mapping ?? [];

        // ✅ Nombres reales de las columnas (solo las que existen)
        $transactionTypeField = $fieldMapping['transaction_type'] ?? 'cod_operacion';
        $dateField = $fieldMapping['date'] ?? 'fecha_operacion';
        $referenceField = $fieldMapping['reference'] ?? 'numero_documento';
        $descriptionField = $fieldMapping['description'] ?? 'conceptos';
        $amountField = $fieldMapping['amount'] ?? 'monto';
        $bankIdField = $fieldMapping['bank_id'] ?? 'cod_banco';

        // ✅ Construir SELECT con los campos que realmente existen
        $selectFields = [
            "{$transactionTypeField} as transaction_type",
            "{$dateField} as date",
            "{$referenceField} as reference",
            "{$descriptionField} as description",
            "{$amountField} as amount",
        ];

        $select = implode(', ', $selectFields);

        // ✅ Construir consulta base
        $query = "SELECT {$select}, B.descripcion AS bank_name,
                (SELECT cambio FROM adm_tabla_tasas_dolar 
                WHERE DATE(fecha) <= DATE(A.{$dateField}) ORDER BY fecha DESC LIMIT 1) AS exchange_rate,
                ROUND(A.monto / (SELECT cambio FROM adm_tabla_tasas_dolar WHERE DATE(fecha) <= DATE(A.fecha_operacion) ORDER BY fecha DESC LIMIT 1), 2) AS amount_converted
              FROM {$connection->table_name} AS A
              INNER JOIN adm_tabla_bancos AS B ON A.{$bankIdField} = B.{$bankIdField}
              WHERE A.{$bankIdField} = :bank_id 
              AND YEAR({$dateField}) = :year 
              AND MONTH({$dateField}) = :month
              AND conciliado = 'Si'";

        $params = [
            ':bank_id' => $externalBankId,
            ':year' => $year,
            ':month' => $month,
        ];


        // ✅ Si hay query_template, usarlo en lugar de la consulta por defecto
        if ($connection->query_template) {
            $query = $connection->query_template;
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        Log::info('📊 Consulta BD externa ejecutada', [
            'table' => $connection->table_name,
            'date_field' => $dateField,
            'results_count' => count($results),
            'external_bank_id' => $externalBankId,
        ]);

        return $results;
    }

    /**
     * Buscar mapeo
     */
    protected function findMapping(array $row, int $companyId, int $connectionId): ?array
    {
        $mapping = MigrationMapping::where('company_id', $companyId)
            ->where('connection_id', $connectionId)
            ->where('source_value', $row['source_value'] ?? '')
            ->first();

        if ($mapping) {
            return [
                'bank_id' => $mapping->target_type === 'bank' ? $mapping->target_id : null,
                'bank_account_id' => $mapping->target_type === 'bank_account' ? $mapping->target_id : null,
                'account_id' => $mapping->target_type === 'account' ? $mapping->target_id : null,
                'category' => $mapping->target_type === 'category' ? $mapping->target_id : null,
            ];
        }

        return null;
    }

    /**
     * Determinar tipo de transacción
     */
    protected function determineType(array $row, string $migrationType): string
    {
        // ✅ Si es 'income' o 'expense' forzado, usarlo
        if ($migrationType === 'income') {
            return TransactionType::INCOME->value;
        }

        if ($migrationType === 'expense') {
            return TransactionType::EXPENSE->value;
        }

        // ✅ Determinar por transaction_type
        if (isset($row['transaction_type'])) {
            $type = strtoupper(trim($row['transaction_type']));

            // NC = Nota de Crédito (Ingreso)
            if ($type === 'NC') {
                return TransactionType::INCOME->value;
            }

            // ND = Nota de Débito (Egreso)
            if ($type === 'ND') {
                return TransactionType::EXPENSE->value;
            }
        }

        // Si es 'all', determinar por el monto o signo
        if (isset($row['amount'])) {
            return $row['amount'] > 0
                ? TransactionType::INCOME->value
                : TransactionType::EXPENSE->value;
        }

        return TransactionType::INCOME->value;
    }

    /**
     * Generar hash para importación
     */
    protected function generateImportHash(array $row, int $companyId): string
    {
        $string = implode('|', [
            $companyId,
            $row['date'] ?? '',
            $row['amount'] ?? 0,
            $row['reference'] ?? '',
            $row['description'] ?? '',
            $row['bank_id'] ?? '',
            $row['transaction_type'] ?? '',
        ]);

        return md5($string);
    }

    /**
     * Buscar o crear cuenta contable
     */
    protected function findOrCreateAccount(ImportedTransaction $item): Account
    {
        // Buscar por nombre
        $account = Account::where('name', $item->description)
            ->orWhere('name', 'LIKE', '%' . substr($item->description, 0, 50) . '%')
            ->first();

        if ($account) {
            return $account;
        }

        // Crear cuenta genérica
        $category = Category::where('type', $item->transaction_type)->first();

        return Account::create([
            'name' => substr($item->description, 0, 100) ?: 'Cuenta Importada',
            'category_id' => $category?->id,
            'description' => 'Cuenta creada automáticamente durante importación',
            'is_system' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Buscar categoría
     */
    protected function findCategory(ImportedTransaction $item): int
    {
        // Si tiene categoría mapeada
        if ($item->mapped_category) {
            $category = Category::where('name', $item->mapped_category)->first();
            if ($category) {
                return $category->id;
            }
        }

        // Buscar categoría por tipo y descripción
        $category = Category::where('type', $item->transaction_type)
            ->where('name', 'LIKE', '%' . substr($item->description, 0, 30) . '%')
            ->first();

        if ($category) {
            return $category->id;
        }

        // Categoría por defecto
        $default = Category::where('type', $item->transaction_type)
            ->where('is_system', true)
            ->first();

        return $default?->id ?? 1;
    }

    /**
     * Buscar un banco en el sistema por su nombre
     */
    protected function findBankByName(string $bankName): ?\App\Models\Bank
    {
        return \App\Models\Bank::where('name', 'LIKE', "%{$bankName}%")
            ->orWhere('name', 'LIKE', "%" . strtoupper($bankName) . "%")
            ->first();
    }

    /**
     * Mapear banco externo a banco del sistema
     */
    protected function mapExternalBank(string $externalBankName): ?int
    {
        $bank = $this->findBankByName($externalBankName);

        if ($bank) {
            Log::info('✅ Banco mapeado correctamente', [
                'external' => $externalBankName,
                'system' => $bank->name,
                'bank_id' => $bank->id,
            ]);
            return $bank->id;
        }

        Log::warning('⚠️ Banco no encontrado en el sistema', [
            'external_bank_name' => $externalBankName,
        ]);

        return null;
    }

    /**
     * Obtener ID de moneda
     */
    protected function getCurrencyId(ImportedTransaction $item): int
    {
        if ($item->original_currency) {
            $currency = $this->currencyService->getBaseCurrency();
            if ($currency) {
                return $currency->id;
            }
        }

        // Moneda por defecto
        return 1;
    }

    /**
     * Construir consulta por defecto
     */
    protected function buildDefaultQuery(string $table, int $year, int $month, string $type): string
    {
        return "SELECT * FROM {$table} 
                WHERE YEAR(date) = :year 
                AND MONTH(date) = :month 
                AND type = :type";
    }
}
