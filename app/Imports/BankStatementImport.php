<?php

namespace App\Imports;

use App\Models\ImportedTransaction;
use App\Models\ImportSession;
use App\Services\BankStatementParserService;
use App\Services\TransactionService;
use App\Services\CurrencyService;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class BankStatementImport implements ToArray, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
{
    use SkipsFailures;

    protected BankStatementParserService $parserService;
    protected TransactionService $transactionService;
    protected CurrencyService $currencyService;

    protected array $config;
    protected int $companyId;
    protected int $bankAccountId;
    protected int $userId;
    protected string $sessionId;

    protected array $stats = [
        'total' => 0,
        'imported' => 0,
        'duplicated' => 0,
        'errors' => 0,
        'error_messages' => [],
    ];

    protected array $importedTransactions = [];

    public function __construct(
        array $config,
        int $companyId,
        int $bankAccountId,
        int $userId,
        string $sessionId
    ) {
        $this->config = $config;
        $this->companyId = $companyId;
        $this->bankAccountId = $bankAccountId;
        $this->userId = $userId;
        $this->sessionId = $sessionId;

        $this->parserService = app(BankStatementParserService::class);
        $this->transactionService = app(TransactionService::class);
        $this->currencyService = app(CurrencyService::class);
    }

    public function array(array $array): void
    {
        Log::info('📊 Filas recibidas en BankStatementImport', [
            'total_filas' => count($array),
            'primeras_filas' => array_slice($array, 0, 3),
        ]);

        $bankConfig = $this->config;
        $patterns = $bankConfig['patterns'] ?? [];

        $columnMap = $patterns['columns'] ?? [
            'date' => 'fecha',
            'reference' => 'referencia',
            'description' => 'descripcion',
            'debit' => 'egreso',
            'credit' => 'ingreso',
            'balance' => 'saldo',
        ];

        $this->stats['total'] = count($array);

        foreach ($array as $rowIndex => $row) {
            try {
                if ($this->isEmptyRow($row)) {
                    Log::info("🔍 Fila {$rowIndex}: Vacía, saltando");
                    continue;
                }

                $transaction = $this->extractFromAssociativeRow($row, $columnMap, $patterns);

                // ✅ LOG PARA VER LA TRANSACCIÓN EXTRAÍDA
                Log::info("🔍 Fila {$rowIndex} - Transacción extraída", [
                    'date' => $transaction['date'] ?? 'null',
                    'description' => substr($transaction['description'] ?? '', 0, 30),
                    'amount' => $transaction['amount'] ?? 0,
                    'type' => $transaction['type'] ?? 'null',
                ]);

                if (!$this->isValidParsedTransaction($transaction)) {
                    $this->stats['errors']++;
                    $this->stats['error_messages'][] = "Fila {$rowIndex}: Datos incompletos o inválidos";
                    Log::warning("⚠️ Fila {$rowIndex}: Transacción inválida", [
                        'transaction' => $transaction,
                    ]);
                    continue;
                }

                // ✅ LOG PARA VERIFICAR DUPLICADOS
                Log::info("🔍 Fila {$rowIndex}: Verificando duplicados...");

                if ($this->isDuplicate($transaction)) {
                    $this->stats['duplicated']++;
                    Log::info("⚠️ Fila {$rowIndex}: DUPLICADO DETECTADO");
                    continue;
                }

                Log::info("✅ Fila {$rowIndex}: No duplicado, creando...");

                $imported = $this->createImportedTransaction($transaction);

                if ($imported) {
                    $this->stats['imported']++;
                    $this->importedTransactions[] = $imported;
                    Log::info("✅ Fila {$rowIndex}: IMPORTADA con ID {$imported->id}");
                }
            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->stats['error_messages'][] = "Fila {$rowIndex}: " . $e->getMessage();
                Log::error("❌ Error en fila {$rowIndex}", [
                    'error' => $e->getMessage(),
                    'data' => $row,
                ]);
            }
        }

        $this->updateImportSession();
    }

    /**
     * ✅ Extraer datos de una fila asociativa
     */
    protected function extractFromAssociativeRow(array $row, array $columnMap, array $patterns): array
    {
        $transaction = [
            'date' => null,
            'reference' => null,
            'description' => null,
            'amount' => 0,
            'type' => null,
            'balance' => null,
            'debit' => null,
            'credit' => null,
        ];

        $fieldMapping = [
            'date' => $columnMap['date'] ?? 'fecha',
            'reference' => $columnMap['reference'] ?? 'referencia',
            'description' => $columnMap['description'] ?? 'descripcion',
            'debit' => $columnMap['debit'] ?? 'egreso',
            'credit' => $columnMap['credit'] ?? 'ingreso',
            'balance' => $columnMap['balance'] ?? 'saldo',
        ];

        foreach ($fieldMapping as $field => $column) {
            if (isset($row[$column])) {
                $value = trim($row[$column]);
                $transaction[$field] = $this->parseValue($value, $field, $patterns);
            }
        }

        // ✅ Procesar montos
        return $this->processAmounts($transaction, $patterns);
    }

    /**
     * ✅ Procesar montos para determinar tipo y valor final
     */
    protected function processAmounts(array $transaction, array $patterns): array
    {
        $amount = 0;
        $type = null;

        // ✅ Verificar si hay debit (egreso) o credit (ingreso)
        $debit = isset($transaction['debit']) && $transaction['debit'] !== null && $transaction['debit'] !== ''
            ? (float) $this->parseNumber($transaction['debit'], $patterns)
            : null;

        $credit = isset($transaction['credit']) && $transaction['credit'] !== null && $transaction['credit'] !== ''
            ? (float) $this->parseNumber($transaction['credit'], $patterns)
            : null;

        // ✅ Si hay egreso (debit)
        if ($debit !== null && $debit != 0) {
            $debitIsNegative = $patterns['debit_negative'] ?? false;
            if ($debit < 0 || $debitIsNegative) {
                $amount = abs($debit);
                $type = 'expense';
            } else {
                $amount = $debit;
                $type = 'expense';  // ✅ Si está en columna Egreso, es gasto
            }
        }

        // ✅ Si hay ingreso (credit)
        if ($credit !== null && $credit != 0) {
            $creditPositive = $patterns['credit_positive'] ?? true;
            if ($credit > 0 || $creditPositive) {
                $amount = abs($credit);
                $type = 'income';
            } else {
                $amount = abs($credit);
                $type = 'income';
            }
        }

        $transaction['amount'] = $amount;
        $transaction['type'] = $type;

        return $transaction;
    }

    /**
     * ✅ Verificar si una fila está vacía
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim($cell) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * ✅ Parsear un valor según su tipo
     */
    protected function parseValue($value, string $key, array $patterns): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim($value);

        if ($key === 'date') {
            return $this->parseDate($value, $patterns);
        }

        if (in_array($key, ['amount', 'debit', 'credit', 'balance'])) {
            return $value; // Se parseará en processAmounts
        }

        return $value;
    }

    /**
     * ✅ Parsear una fecha
     */
    protected function parseDate($value, array $patterns): ?string
    {
        if (is_numeric($value) && $value > 0) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                // Fallback
            }
        }

        $dateFormats = $patterns['date_formats'] ?? ['dd/mm/yyyy', 'yyyy-mm-dd'];

        foreach ($dateFormats as $format) {
            $parsed = $this->parseDateWithFormat($value, $format);
            if ($parsed) {
                return $parsed;
            }
        }

        try {
            $carbon = \Carbon\Carbon::parse($value);
            return $carbon->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * ✅ Parsear fecha con formato específico
     */
    protected function parseDateWithFormat(string $value, string $format): ?string
    {
        try {
            $date = match ($format) {
                'dd/mm/yyyy' => \DateTime::createFromFormat('d/m/Y', $value),
                'dd-mm-yyyy' => \DateTime::createFromFormat('d-m-Y', $value),
                'd/m/yyyy' => \DateTime::createFromFormat('d/m/Y', $value),
                'd/m/yy' => \DateTime::createFromFormat('d/m/y', $value),
                'mm/dd/yyyy' => \DateTime::createFromFormat('m/d/Y', $value),
                'yyyy-mm-dd' => \DateTime::createFromFormat('Y-m-d', $value),
                default => \DateTime::createFromFormat($format, $value),
            };

            if ($date) {
                return $date->format('Y-m-d');
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * ✅ Parsear un número con formato venezolano
     */
    protected function parseNumber($value, array $patterns): float
    {
        if (is_string($value)) {
            $value = preg_replace('/[^\d.,-]/', '', $value);

            $isVenezuelan = ($patterns['amount_format'] ?? '') === 'venezuelan' ||
                (strpos($value, '.') !== false && strpos($value, ',') !== false &&
                    strrpos($value, '.') < strrpos($value, ','));

            if ($isVenezuelan) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        }

        return (float) $value;
    }

    /**
     * ✅ Validar si la transacción es válida
     */
    protected function isValidParsedTransaction(array $transaction): bool
    {
        $hasDate = isset($transaction['date']) && $transaction['date'] !== null && $transaction['date'] !== '';
        $hasAmount = isset($transaction['amount']) && $transaction['amount'] > 0;
        $hasDescription = isset($transaction['description']) && $transaction['description'] !== null && $transaction['description'] !== '';

        // ✅ Si tiene fecha y monto, es válida (aunque no tenga descripción)
        if ($hasDate && $hasAmount) {
            return true;
        }

        // ✅ Si tiene descripción y monto, es válida (aunque no tenga fecha)
        if ($hasDescription && $hasAmount) {
            return true;
        }

        return false;
    }

    /**
     * ✅ Verificar duplicados usando hash
     */
    protected function isDuplicate(array $transaction): bool
    {
        // ✅ Asegurar que tenemos los datos necesarios
        if (empty($transaction['amount']) || empty($transaction['date'])) {
            Log::warning('⚠️ isDuplicate: Datos incompletos', [
                'amount' => $transaction['amount'] ?? 'null',
                'date' => $transaction['date'] ?? 'null',
            ]);
            return false;
        }

        $hashString = implode('|', [
            $this->companyId,
            $transaction['amount'] ?? 0,
            $transaction['date'] ?? '',
            $transaction['reference'] ?? '',
            substr($transaction['description'] ?? '', 0, 50),
        ]);

        $hash = md5($hashString);

        // ✅ Log para ver el hash generado
        Log::info('🔍 Verificando duplicado', [
            'hash' => $hash,
            'amount' => $transaction['amount'],
            'date' => $transaction['date'],
        ]);

        // ✅ Buscar en TODOS los registros de la misma empresa (cualquier sesión)
        $exists = ImportedTransaction::where('company_id', $this->companyId)
            ->where('hash', $hash)
            ->exists();

        if ($exists) {
            Log::info('⚠️ DUPLICADO DETECTADO', [
                'hash' => $hash,
                'date' => $transaction['date'],
                'amount' => $transaction['amount'],
            ]);
            return true;
        }

        Log::info('✅ No duplicado', ['hash' => $hash]);
        return false;
    }

    /**
     * ✅ Crear transacción importada con hash y bank_id
     */
    protected function createImportedTransaction(array $transaction): ?ImportedTransaction
    {
        try {
            $hashString = implode('|', [
                $this->companyId,
                $transaction['amount'] ?? 0,
                $transaction['date'] ?? '',
                $transaction['reference'] ?? '',
                substr($transaction['description'] ?? '', 0, 50),
            ]);

            $hash = md5($hashString);

            Log::info('🔍 Creando transacción importada', [
                'hash' => $hash,
                'amount' => $transaction['amount'],
                'date' => $transaction['date'],
            ]);

            // ✅ Asegurar que el hash NO sea NULL
            return ImportedTransaction::create([
                'company_id' => $this->companyId,
                'bank_id' => $this->config['bank_id'] ?? null,
                'bank_name' => $this->config['name'] ?? null,
                'bank_account_id' => $this->bankAccountId,
                'transaction_date' => $transaction['date'] ?? now()->toDateString(),
                'reference' => $transaction['reference'] ?? null,
                'description' => $transaction['description'] ?? 'Transacción importada',
                'amount' => $transaction['amount'] ?? 0,
                'transaction_type' => $transaction['type'] ?? 'income',
                'is_processed' => false,
                'import_session_id' => $this->sessionId,
                'hash' => $hash,  // ✅ Nunca será NULL
            ]);
        } catch (\Exception $e) {
            Log::error("❌ Error creando transacción importada", [
                'error' => $e->getMessage(),
                'transaction' => $transaction,
            ]);
            return null;
        }
    }

    /**
     * Actualizar la sesión de importación
     */
    protected function updateImportSession(): void
    {
        try {
            $session = ImportSession::find($this->sessionId);
            if ($session) {
                $session->update([
                    'total_rows' => $this->stats['total'],
                    'processed_rows' => $this->stats['imported'],
                    'duplicated_rows' => $this->stats['duplicated'],
                    'error_rows' => $this->stats['errors'],
                    'metadata' => array_merge($session->metadata ?? [], [
                        'stats' => $this->stats,
                        'error_messages' => $this->stats['error_messages'],
                    ]),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error actualizando sesión", [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function getImportedTransactions(): array
    {
        return $this->importedTransactions;
    }

    public function headingRow(): int
    {
        return (int) ($this->config['patterns']['header_row_index'] ?? 1);
    }

    public function rules(): array
    {
        return [];
    }
}
