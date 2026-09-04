<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImportSession extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'company_id',
        'user_id',
        'source',
        'file_name',
        'file_path',
        'total_rows',
        'processed_rows',
        'duplicated_rows',
        'error_rows',
        'status',
        'error_log',
        'metadata',
        'started_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'error_log' => 'array',
        'metadata' => 'array',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'duplicated_rows' => 'integer',
        'error_rows' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ================================================================
    // FUENTES Y ESTADOS
    // ================================================================

    const SOURCE_EXCEL = 'excel';
    const SOURCE_MIGRATION = 'migration';
    const SOURCE_CSV = 'csv';
    const SOURCE_PDF = 'pdf';

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Get all sources with labels.
     */
    public static function getSources(): array
    {
        return [
            self::SOURCE_EXCEL => 'Excel',
            self::SOURCE_MIGRATION => 'Migración',
            self::SOURCE_CSV => 'CSV',
            self::SOURCE_PDF => 'PDF',
        ];
    }

    /**
     * Get all statuses with labels.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_PROCESSING => 'Procesando',
            self::STATUS_COMPLETED => 'Completado',
            self::STATUS_FAILED => 'Fallido',
        ];
    }

    // ================================================================
    // RELACIONES
    // ================================================================

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    // ================================================================
    // MÉTODOS DE AYUDA
    // ================================================================

    public function getSourceLabelAttribute(): string
    {
        return self::getSources()[$this->source] ?? $this->source;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return [
            self::STATUS_PENDING => 'warning',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
        ][$this->status] ?? 'gray';
    }

    public function getProgressAttribute(): float
    {
        if ($this->total_rows == 0) {
            return 0;
        }
        return round(($this->processed_rows / $this->total_rows) * 100, 2);
    }

    public function getSuccessRateAttribute(): float
    {
        $total = $this->processed_rows + $this->error_rows;
        if ($total == 0) {
            return 0;
        }
        return round(($this->processed_rows / $total) * 100, 2);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function canStart(): bool
    {
        return $this->isPending();
    }

    public function canRetry(): bool
    {
        return $this->isFailed();
    }

    public function start(): self
    {
        $this->status = self::STATUS_PROCESSING;
        $this->started_at = now();
        return $this;
    }

    public function complete(): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        return $this;
    }

    public function fail(string $error = null): self
    {
        $this->status = self::STATUS_FAILED;
        $this->completed_at = now();
        if ($error) {
            $this->error_log = array_merge($this->error_log ?? [], [$error]);
        }
        return $this;
    }
}