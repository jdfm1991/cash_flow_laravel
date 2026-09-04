<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MigrationMapping extends Model
{
    protected $table = 'migration_mappings';

    protected $fillable = [
        'company_id',
        'connection_id',
        'source_table',
        'source_field',
        'source_value',
        'target_type', // 'account', 'bank', 'category'
        'target_id',
    ];

    // ================================================================
    // RELACIONES
    // ================================================================

    public function company()
    {
        return $this->belongsTo(Company::class);
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

    public function scopeByConnection($query, int $connectionId)
    {
        return $query->where('connection_id', $connectionId);
    }

    public function scopeBySource($query, string $table, string $field, string $value)
    {
        return $query->where('source_table', $table)
            ->where('source_field', $field)
            ->where('source_value', $value);
    }

    // ================================================================
    // MÉTODOS DE NEGOCIO
    // ================================================================

    public function getTarget()
    {
        switch ($this->target_type) {
            case 'account':
                return Account::find($this->target_id);
            case 'bank':
                return Bank::find($this->target_id);
            case 'category':
                return Category::find($this->target_id);
            default:
                return null;
        }
    }
}