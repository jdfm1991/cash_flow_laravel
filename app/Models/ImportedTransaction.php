<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportedTransaction extends Model
{
    protected $table = 'imported_transactions';

    protected $fillable = [
        'company_id',
        'bank_id',
        'bank_name',
        'bank_account_id',
        'transaction_date',
        'reference',
        'description',
        'amount',
        'transaction_type', // 'income' o 'expense'
        'amount_converted',
        'currency_id',
        'exchange_rate',
        'is_processed',
        'mapped_account_id',
        'mapped_category',
        'import_session_id',
        'hash',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'amount_converted' => 'decimal:4',
        'exchange_rate' => 'decimal:8',
        'transaction_date' => 'date',
        'is_processed' => 'boolean',
    ];

    // ================================================================
    // RELACIONES
    // ================================================================

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function importSession()
    {
        return $this->belongsTo(ImportSession::class, 'import_session_id', 'id');
    }

    public function mappedAccount()
    {
        return $this->belongsTo(Account::class, 'mapped_account_id');
    }

    // ================================================================
    // SCOPES
    // ================================================================

    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    public function scopeBySession($query, string $sessionId)
    {
        return $query->where('import_session_id', $sessionId);
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // ================================================================
    // MÉTODOS DE NEGOCIO
    // ================================================================

    public function markAsProcessed(int $accountId): bool
    {
        return $this->update([
            'is_processed' => true,
            'mapped_account_id' => $accountId,
        ]);
    }

    public function getHash(): string
    {
        return md5(
            $this->company_id .
            $this->amount .
            $this->transaction_date .
            ($this->reference ?? '') .
            $this->transaction_type
        );
    }
}