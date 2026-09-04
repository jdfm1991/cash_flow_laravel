<?php

namespace App\Services;

use App\Models\ExternalConnection;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use PDO;
use PDOException;

class ExternalConnectionService
{
    /**
     * Probar una conexión a base de datos externa
     */
    public function testConnection(array $data): array
    {
        try {
            $dsn = $this->buildDsn($data);

            $pdo = new PDO(
                $dsn,
                $data['username'],
                $data['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5, // Timeout de 5 segundos
                ]
            );

            // Probar conexión ejecutando una consulta simple
            $pdo->query("SELECT 1");
            $pdo = null;

            return [
                'success' => true,
                'message' => 'Conexión exitosa a la base de datos externa.',
            ];
        } catch (PDOException $e) {
            Log::warning("Error de conexión a base de datos externa", [
                'host' => $data['host'] ?? 'unknown',
                'db' => $data['db_name'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error de conexión: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error inesperado: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Crear una nueva conexión externa
     */
    public function create(array $data): ExternalConnection
    {
        // Encriptar la contraseña
        if (isset($data['password'])) {
            $data['password'] = Crypt::encryptString($data['password']);
        }

        // Establecer valores por defecto
        $data['port'] = $data['port'] ?? 3306;
        $data['type'] = $data['type'] ?? 'migration';
        $data['is_active'] = $data['is_active'] ?? true;

        $connection = ExternalConnection::create($data);

        Log::info("Conexión externa creada", [
            'connection_id' => $connection->id,
            'name' => $connection->name,
            'host' => $connection->host,
            'company_id' => $connection->company_id,
        ]);

        return $connection;
    }

    /**
     * Actualizar una conexión externa
     */
    public function update(ExternalConnection $connection, array $data): ExternalConnection
    {
        // Si se proporciona una nueva contraseña, encriptarla
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Crypt::encryptString($data['password']);
        } else {
            unset($data['password']); // Mantener la contraseña actual
        }

        $connection->update($data);

        Log::info("Conexión externa actualizada", [
            'connection_id' => $connection->id,
            'name' => $connection->name,
        ]);

        return $connection->fresh();
    }

    /**
     * Eliminar una conexión externa
     */
    public function delete(ExternalConnection $connection): bool
    {
        // Verificar si tiene logs de migración asociados
        if ($connection->migrationLogs()->count() > 0) {
            throw new \Exception(
                'No se puede eliminar la conexión porque tiene migraciones asociadas. ' .
                    'Elimina primero los logs de migración.'
            );
        }

        $result = $connection->delete();

        Log::info("Conexión externa eliminada", [
            'connection_id' => $connection->id,
            'name' => $connection->name,
        ]);

        return $result;
    }

    /**
     * Obtener años disponibles en la base de datos externa
     */
    public function getAvailableYears(ExternalConnection $connection): array
    {
        $db = $this->getPdoConnection($connection);

        // ✅ Obtener el nombre real de la columna de fecha desde field_mapping
        $fieldMapping = $connection->field_mapping ?? [];
        $dateField = $fieldMapping['date'] ?? 'date';

        $query = "SELECT DISTINCT YEAR({$dateField}) as year 
              FROM {$connection->table_name} 
              WHERE {$dateField} IS NOT NULL 
              AND conciliado = 'Si'
              ORDER BY year DESC";

        $stmt = $db->prepare($query);

        try {
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $years = array_column($results, 'year');

            $years = array_filter($years, fn($year) => $year >= 2000 && $year <= (date('Y') + 1));

            return array_values($years);
        } catch (PDOException $e) {
            Log::error("Error obteniendo años de base de datos externa", [
                'connection_id' => $connection->id,
                'date_field' => $dateField,
                'error' => $e->getMessage(),
            ]);

            return range(date('Y') - 5, date('Y'));
        }
    }

    /**
     * Obtener meses disponibles en la base de datos externa para un año específico
     */
    public function getAvailableMonths(ExternalConnection $connection, int $year): array
    {
        $db = $this->getPdoConnection($connection);

        // ✅ Obtener el nombre real de la columna de fecha desde field_mapping
        $fieldMapping = $connection->field_mapping ?? [];
        $dateField = $fieldMapping['date'] ?? 'date';

        $query = "SELECT DISTINCT MONTH({$dateField}) as month 
              FROM {$connection->table_name} 
              WHERE YEAR({$dateField}) = :year 
              AND {$dateField} IS NOT NULL 
              AND conciliado = 'Si'
              ORDER BY month ASC";

        $stmt = $db->prepare($query);
        $stmt->execute(['year' => $year]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $months = array_column($results, 'month');
        $months = array_filter($months, fn($m) => $m >= 1 && $m <= 12);

        return array_values($months);
    }

    /**
     * Obtener bancos disponibles en la base de datos externa
     */
    public function getAvailableBanks(ExternalConnection $connection, int $year, int $month): array
    {
        $db = $this->getPdoConnection($connection);

        // ✅ Obtener el nombre real de la columna de fecha desde field_mapping
        $fieldMapping = $connection->field_mapping ?? [];
        $dateField = $fieldMapping['date'] ?? 'date';
        $bankIdField = $fieldMapping['bank_id'] ?? 'bank_id';

        $query = "SELECT DISTINCT B.{$bankIdField} as bank_id, B.descripcion as bank_name 
              FROM {$connection->table_name} AS A
              INNER JOIN adm_tabla_bancos AS B ON A.cod_banco = B.cod_banco
              WHERE YEAR({$dateField}) = :year 
              AND MONTH({$dateField}) = :month 
              AND {$dateField} IS NOT NULL
              AND conciliado = 'Si' 
              ORDER BY B.{$bankIdField} ASC";

        $stmt = $db->prepare($query);
        $stmt->execute(['year' => $year, 'month' => $month]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return [[
                'bank_id' => 0,
                'bank_name' => 'Banco sin identificar',
            ]];
        }

        return $results;
    }

    /**
     * Obtener una conexión PDO a la base de datos externa
     */
    public function getPdoConnection(ExternalConnection $connection): PDO
    {
        $dsn = $this->buildDsn($connection->toArray());

        // ✅ Obtener la contraseña desencriptada
        $password = $connection->getDecryptedPassword();

        return new PDO(
            $dsn,
            $connection->username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 30,
            ]
        );
    }

    /**
     * Construir el DSN para la conexión
     */
    protected function buildDsn(array $data): string
    {
        $host = $data['host'] ?? 'localhost';
        $port = $data['port'] ?? 3306;
        $dbName = $data['db_name'] ?? '';

        return sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $dbName
        );
    }

    /**
     * Obtener todas las conexiones de una empresa
     */
    public function getByCompany(int $companyId, bool $onlyActive = true)
    {
        $query = ExternalConnection::where('company_id', $companyId);

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Obtener conexiones para migración (tipo migration)
     */
    public function getMigrationConnections(int $companyId)
    {
        return $this->getByCompany($companyId)
            ->where('type', 'migration');
    }

    /**
     * Probar si una conexión existe y está activa
     */
    public function isConnectionActive(int $connectionId): bool
    {
        $connection = ExternalConnection::find($connectionId);

        if (!$connection || !$connection->is_active) {
            return false;
        }

        return $this->testConnection($connection->toArray())['success'];
    }

    /**
     * Obtener el estado de una conexión
     */
    public function getConnectionStatus(ExternalConnection $connection): array
    {
        $testResult = $this->testConnection($connection->toArray());

        return [
            'id' => $connection->id,
            'name' => $connection->name,
            'is_active' => $connection->is_active,
            'last_sync_at' => $connection->last_sync_at,
            'connection_ok' => $testResult['success'],
            'connection_error' => $testResult['success'] ? null : $testResult['message'],
            'has_mappings' => $connection->mappings()->count() > 0,
            'has_migration_logs' => $connection->migrationLogs()->count() > 0,
        ];
    }
}
