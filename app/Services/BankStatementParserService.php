<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class BankStatementParserService
{
    /**
     * Configuración de bancos (cargada desde import.php)
     */
    protected array $bankConfigs = [];

    public function __construct()
    {
        $this->bankConfigs = config('import.banks', []);
    }

    /**
     * Parsear un archivo de extracto bancario
     */
    public function parse(string $filePath, int $bankId, ?int $bankAccountId = null): array
    {
        // 1. Obtener configuración del banco
        $config = $this->getBankConfig($bankId);

        if (!$config) {
            throw new \Exception("No se encontró configuración para el banco ID: {$bankId}");
        }

        // 2. Obtener la ruta completa del archivo
        $fullPath = $this->resolveFilePath($filePath);

        // 3. Cargar el archivo
        $spreadsheet = $this->loadSpreadsheet($fullPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // 4. Identificar inicio de datos
        $startRow = $this->findStartRow($rows, $config);
        $headerRow = $this->findHeaderRow($rows, $config);

        // 5. Extraer transacciones
        $transactions = $this->extractTransactions($rows, $config, $startRow, $headerRow);

        // 6. Enriquecer con metadatos
        return [
            'bank_id' => $bankId,
            'bank_name' => $config['name'] ?? 'Banco sin nombre',
            'bank_account_id' => $bankAccountId,
            'total_rows' => count($transactions),
            'transactions' => $transactions,
            'start_row' => $startRow,
            'header_row' => $headerRow,
            'config_used' => $config,
            'parsed_at' => now()->toDateTimeString(),
        ];
    }

    //
    protected function resolveFilePath(string $filePath): string
    {
        // Si ya es una ruta absoluta, devolverla
        if (file_exists($filePath)) {
            return $filePath;
        }

        // Verificar en storage/public
        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->path($filePath);
        }

        // Verificar en storage/app
        $fullPath = storage_path('app/' . $filePath);
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        // Verificar en storage/app/public
        $fullPath = storage_path('app/public/' . $filePath);
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        throw new \Exception("Archivo no encontrado: {$filePath}");
    }

    /**
     * Cargar el archivo con PhpSpreadsheet
     */
    protected function loadSpreadsheet(string $filePath)
    {
        // ✅ Verificar si la ruta existe directamente
        if (file_exists($filePath)) {
            $fullPath = $filePath;
        } else {
            // ✅ Buscar en diferentes ubicaciones
            $possiblePaths = [
                $filePath,
                storage_path('app/' . $filePath),
                storage_path('app/private/' . $filePath),
                storage_path('app/public/' . $filePath),
                storage_path('app/livewire-tmp/' . basename($filePath)),
            ];

            $fullPath = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $fullPath = $path;
                    break;
                }
            }

            if (!$fullPath) {
                throw new \Exception("Archivo no encontrado en ninguna ubicación: {$filePath}");
            }
        }

        try {
            return IOFactory::load($fullPath);
        } catch (\Exception $e) {
            throw new \Exception("Error al leer el archivo: " . $e->getMessage());
        }
    }

    /**
     * Encontrar la fila donde comienzan los datos
     */
    protected function findStartRow(array $rows, array $config): int
    {
        $patterns = $config['patterns'] ?? [];

        // Si hay start_row definido, usarlo
        if (isset($patterns['start_row'])) {
            return (int) $patterns['start_row'] - 1; // Convertir a índice 0
        }

        // Si hay start_row_offset, usarlo
        if (isset($patterns['start_row_offset'])) {
            return (int) $patterns['start_row_offset'];
        }

        // Buscar fila con palabras clave de encabezado
        $headerKeywords = $patterns['header_row_keywords'] ?? ['Fecha', 'Fecha de transacción', 'Fecha de pago'];

        foreach ($rows as $index => $row) {
            $rowText = implode(' ', array_filter($row));

            // Verificar si la fila contiene alguna palabra clave
            foreach ($headerKeywords as $keyword) {
                if (stripos($rowText, $keyword) !== false) {
                    return $index + 1; // Devolver la siguiente fila (inicio de datos)
                }
            }
        }

        // Si no se encuentra, asumir que los datos empiezan en la fila 2
        return 1;
    }

    /**
     * Encontrar la fila de encabezados
     */
    protected function findHeaderRow(array $rows, array $config): ?int
    {
        $patterns = $config['patterns'] ?? [];

        // Si hay header_row_index definido, usarlo
        if (isset($patterns['header_row_index'])) {
            return (int) $patterns['header_row_index'] - 1;
        }

        // Buscar fila con encabezados
        $headerKeywords = $patterns['header_row_keywords'] ?? ['Fecha', 'Descripción', 'Monto'];

        foreach ($rows as $index => $row) {
            $rowText = implode(' ', array_filter($row));

            $matchCount = 0;
            foreach ($headerKeywords as $keyword) {
                if (stripos($rowText, $keyword) !== false) {
                    $matchCount++;
                }
            }

            if ($matchCount >= 2) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Extraer transacciones del archivo
     */
    protected function extractTransactions(array $rows, array $config, int $startRow, ?int $headerRow): array
    {
        $patterns = $config['patterns'] ?? [];
        $columns = $patterns['columns'] ?? [];
        $transactions = [];
        $skipKeywords = $patterns['skip_rows_with_keywords'] ?? [];

        // Si no hay columnas definidas, usar mapeo por defecto
        if (empty($columns)) {
            $columns = $this->getDefaultColumnMapping();
        }

        for ($i = $startRow; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Saltar filas vacías
            if ($this->isEmptyRow($row)) {
                continue;
            }

            // Saltar filas con palabras clave
            $rowText = implode(' ', array_filter($row));
            $skip = false;
            foreach ($skipKeywords as $keyword) {
                if (stripos($rowText, $keyword) !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            // Extraer datos según columnas
            $transaction = $this->extractRowData($row, $columns, $patterns);

            // Validar que la transacción tenga datos mínimos
            if ($this->isValidTransaction($transaction)) {
                $transactions[] = $transaction;
            }
        }

        return $transactions;
    }

    /**
     * Extraer datos de una fila según el mapeo de columnas
     */
    protected function extractRowData(array $row, array $columns, array $patterns): array
    {
        $transaction = [
            'date' => null,
            'reference' => null,
            'description' => null,
            'amount' => 0,
            'type' => null, // 'income' o 'expense'
            'balance' => null,
            'debit' => null,
            'credit' => null,
            'raw' => $row,
        ];

        // Mapeo de columnas
        $columnMap = [
            'date' => 'date',
            'reference' => 'reference',
            'description' => 'description',
            'amount' => 'amount',
            'debit' => 'debit',
            'credit' => 'credit',
            'balance' => 'balance',
            'movement_type' => 'movement_type',
        ];

        foreach ($columnMap as $key => $column) {
            if (isset($columns[$key])) {
                $colIndex = $this->getColumnIndex($columns[$key]);
                if ($colIndex < count($row)) {
                    $value = trim($row[$colIndex] ?? '');
                    $transaction[$key] = $this->parseValue($value, $key, $patterns);
                }
            }
        }

        // Procesar amount, debit y credit para determinar tipo y monto
        $transaction = $this->processAmounts($transaction, $patterns);

        return $transaction;
    }

    /**
     * Procesar montos para determinar tipo y valor final
     */
    protected function processAmounts(array $transaction, array $patterns): array
    {
        $amount = 0;
        $type = null;

        // Si hay debit y credit separados
        if (isset($transaction['debit']) && $transaction['debit'] !== null) {
            $debit = (float) $transaction['debit'];
            $credit = (float) ($transaction['credit'] ?? 0);

            // Verificar si debit es negativo (algunos bancos lo guardan así)
            if ($debit < 0 || ($patterns['debit_negative'] ?? false)) {
                $amount = abs($debit);
                $type = 'expense';
            } elseif ($debit > 0) {
                $amount = $debit;
                $type = 'income';
            }

            if ($credit > 0) {
                $amount = $credit;
                $type = 'income';
            }

            // Si solo hay amount
        } elseif (isset($transaction['amount']) && $transaction['amount'] !== null) {
            $rawAmount = (float) $transaction['amount'];

            // Determinar tipo por signo
            if ($rawAmount < 0) {
                $amount = abs($rawAmount);
                $type = 'expense';
            } elseif ($rawAmount > 0) {
                $amount = $rawAmount;
                $type = 'income';
            }

            // Usar movimiento_type si está disponible
            if (isset($transaction['movement_type'])) {
                $movementType = strtolower($transaction['movement_type']);
                $creditKeywords = $patterns['credit_keywords'] ?? ['credito', 'abono', 'ingreso', 'nota de credito'];
                $debitKeywords = $patterns['debit_keywords'] ?? ['debito', 'cargo', 'egreso', 'nota de debito'];

                foreach ($creditKeywords as $keyword) {
                    if (stripos($movementType, $keyword) !== false) {
                        $type = 'income';
                        break;
                    }
                }

                foreach ($debitKeywords as $keyword) {
                    if (stripos($movementType, $keyword) !== false) {
                        $type = 'expense';
                        break;
                    }
                }
            }
        }

        $transaction['amount'] = $amount;
        $transaction['type'] = $type;

        return $transaction;
    }

    /**
     * Obtener la configuración de un banco
     */
    public function getBankConfig(int $bankId): ?array
    {
        // Buscar por ID en la configuración
        if (isset($this->bankConfigs[$bankId])) {
            return $this->bankConfigs[$bankId];
        }

        // Buscar por código (alternativa)
        $bankIdStr = (string) $bankId;
        foreach ($this->bankConfigs as $id => $config) {
            if ($id === $bankIdStr) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Obtener configuración de un banco por su código
     */
    public function getBankConfigByCode(string $code): ?array
    {
        foreach ($this->bankConfigs as $config) {
            if (isset($config['code']) && $config['code'] === $code) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Obtener todos los bancos configurados
     */
    public function getConfiguredBanks(): array
    {
        $result = [];
        foreach ($this->bankConfigs as $id => $config) {
            $result[] = [
                'id' => (int) $id,
                'name' => $config['name'] ?? 'Banco ' . $id,
                'code' => $config['code'] ?? null,
                'has_patterns' => isset($config['patterns']) && !empty($config['patterns']),
            ];
        }
        return $result;
    }

    /**
     * Validar si una transacción tiene datos mínimos
     */
    protected function isValidTransaction(array $transaction): bool
    {
        // Debe tener al menos fecha o descripción y un monto > 0
        $hasDate = $transaction['date'] !== null && $transaction['date'] !== '';
        $hasDescription = $transaction['description'] !== null && $transaction['description'] !== '';
        $hasAmount = isset($transaction['amount']) && $transaction['amount'] > 0;

        return ($hasDate || $hasDescription) && $hasAmount;
    }

    /**
     * Verificar si una fila está vacía
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && $cell !== '' && $cell !== ' ') {
                return false;
            }
        }
        return true;
    }

    /**
     * Obtener el índice de una columna (A=0, B=1, etc.)
     */
    protected function getColumnIndex(string $column): int
    {
        // Si es número, usarlo directamente
        if (is_numeric($column)) {
            return (int) $column - 1;
        }

        // Convertir letra a índice (A=0, B=1, ...)
        return Coordinate::columnIndexFromString($column) - 1;
    }

    /**
     * Parsear un valor según su tipo
     */
    protected function parseValue($value, string $key, array $patterns): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Limpiar espacios
        $value = trim($value);

        // Si es fecha
        if ($key === 'date') {
            return $this->parseDate($value, $patterns);
        }

        // Si es número (monto)
        if (in_array($key, ['amount', 'debit', 'credit', 'balance'])) {
            return $this->parseNumber($value, $patterns);
        }

        return $value;
    }

    /**
     * Parsear una fecha
     */
    protected function parseDate($value, array $patterns): ?string
    {
        // Si es número serial de Excel
        if (is_numeric($value) && $value > 0) {
            try {
                $date = Date::excelToDateTimeObject($value);
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                // Si falla, intentar como fecha normal
            }
        }

        // Intentar parsear como fecha con los formatos configurados
        $dateFormats = $patterns['date_formats'] ?? ['dd/mm/yyyy', 'yyyy-mm-dd'];

        foreach ($dateFormats as $format) {
            $parsed = $this->parseDateWithFormat($value, $format);
            if ($parsed) {
                return $parsed;
            }
        }

        // Intentar con Carbon
        try {
            $carbon = \Carbon\Carbon::parse($value);
            return $carbon->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parsear fecha con formato específico
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
                'd de m de yyyy' => \DateTime::createFromFormat('d \d\e F \d\e Y', $value),
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
     * Parsear un número
     */
    protected function parseNumber($value, array $patterns): float
    {
        // Si es string, limpiar
        if (is_string($value)) {
            // Eliminar caracteres no numéricos excepto punto y coma
            $value = preg_replace('/[^\d.,-]/', '', $value);

            // Formato venezolano: 1.234,56
            if (($patterns['amount_format'] ?? '') === 'venezuelan' ||
                (strpos($value, '.') !== false && strpos($value, ',') !== false &&
                    strrpos($value, '.') < strrpos($value, ','))
            ) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                // Estándar: 1,234.56
                $value = str_replace(',', '', $value);
            }
        }

        return (float) $value;
    }

    /**
     * Obtener mapeo de columnas por defecto
     */
    protected function getDefaultColumnMapping(): array
    {
        return [
            'date' => 'A',
            'reference' => 'B',
            'description' => 'C',
            'amount' => 'D',
        ];
    }
}
