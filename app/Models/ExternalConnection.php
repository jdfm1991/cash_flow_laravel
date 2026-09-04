<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PDO;

class ExternalConnection extends Model
{
    use SoftDeletes;

    protected $table = 'external_connections';

    protected $connection = 'mysql';

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'host',
        'port',
        'db_name',
        'username',
        'password',
        'table_name',
        'field_mapping',
        'query_template',
        'last_sync_at',
        'is_active',
    ];

    protected $casts = [
        'field_mapping' => 'array',
        'last_sync_at' => 'datetime',
        'is_active' => 'boolean',
        'port' => 'integer',
    ];

    protected $hidden = [
        'password',
    ];

    // ================================================================
    // RELACIONES
    // ================================================================

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function migrationLogs()
    {
        return $this->hasMany(MigrationLog::class, 'connection_id');
    }

    public function mappings()
    {
        return $this->hasMany(MigrationMapping::class, 'connection_id');
    }

    // ================================================================
    // MÉTODOS DE NEGOCIO

    /**
     * Probar la conexión a la base de datos externa
     * ✅ Usa el servicio, no conexión dinámica
     */
    public function testConnection(): array
    {
        try {
            $service = app(\App\Services\ExternalConnectionService::class);
            return $service->testConnection($this->toArray());
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Actualizar la fecha de última sincronización
     */
    public function updateLastSync(): bool
    {
        return $this->update(['last_sync_at' => now()]);
    }

    /**
     * Obtener la contraseña desencriptada
     */
    public function getDecryptedPassword(): string
    {
        try {
            return Crypt::decryptString($this->password);
        } catch (\Exception $e) {
            // Si no está encriptada, devolver como está
            return $this->password;
        }
    }

    /**
     * Obtener datos de conexión para prueba
     */
    public function getConnectionTestData(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'db_name' => $this->db_name,
            'username' => $this->username,
            'password' => $this->getDecryptedPassword(),
        ];
    }

    /**
     * Obtener una conexión PDO a la base de datos externa
     * ✅ Usa el servicio, no conexión dinámica
     */
    public function getPdoConnection(): PDO
    {
        $service = app(\App\Services\ExternalConnectionService::class);
        return $service->getPdoConnection($this);
    }
}
