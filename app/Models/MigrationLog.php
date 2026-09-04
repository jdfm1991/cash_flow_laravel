<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MigrationLog extends Model
{
    protected $table = 'migration_logs';

    protected $fillable = [
        'company_id',
        'connection_id',
        'migration_type', // 'income', 'expense', 'all'
        'year',
        'month',
        'total_records',
        'imported_records',
        'duplicated_records',
        'failed_records',
        'status', // 'pending', 'processing', 'completed', 'failed'
        'error_log',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'total_records' => 'integer',
        'imported_records' => 'integer',
        'duplicated_records' => 'integer',
        'failed_records' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'error_log' => 'array',
    ];

    // ================================================================
    // RELACIONES
    // ================================================================

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function connection()
    {
        return $this->belongsTo(ExternalConnection::class, 'connection_id');
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    // ================================================================
    // MÉTODOS DE NEGOCIO
    // ================================================================

    public function start()
    {
        return $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function complete()
    {
        return $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function fail(string $error = null)
    {
        $errorLog = $this->error_log ?? [];
        if ($error) {
            $errorLog[] = $error;
        }

        return $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_log' => $errorLog,
        ]);
    }

    public function getProgressAttribute(): float
    {
        if ($this->total_records == 0) {
            return 0;
        }
        return round(($this->imported_records / $this->total_records) * 100, 2);
    }
}