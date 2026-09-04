<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'bank_accounts';

    /**
     * The attributes that are mass assignable.
     * 
     * Mapeo de campos del sistema actual a Laravel:
     * - current_balance → NO SE ALMACENA (se calcula)
     */
    protected $fillable = [
        'company_id',
        'bank_id',
        'currency_id',
        'account_number',
        'alias',
        'account_type',
        'account_holder',
        'opening_balance',
        'opened_at',
        'notes',
        'is_active',
        'is_default',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'opening_balance' => 'decimal:4',
        'opened_at' => 'date',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    // ================================================================
    // TIPOS DE CUENTA
    // ================================================================

    const TYPE_CORRIENTE = 'corriente';
    const TYPE_AHORROS = 'ahorros';
    const TYPE_NOMINA = 'nomina';
    const TYPE_INVERSION = 'inversion';
    const TYPE_CAJA_CHICA = 'caja_chica';
    const TYPE_EFECTIVO = 'efectivo';
    const TYPE_TARJETA_CREDITO = 'tarjeta_credito';
    const TYPE_VIRTUAL = 'virtual';

    /**
     * Get all account types with labels.
     */
    public static function getAccountTypes(): array
    {
        return [
            self::TYPE_CORRIENTE => 'Cuenta Corriente',
            self::TYPE_AHORROS => 'Cuenta de Ahorros',
            self::TYPE_NOMINA => 'Cuenta de Nómina',
            self::TYPE_INVERSION => 'Cuenta de Inversión',
            self::TYPE_CAJA_CHICA => 'Caja Chica',
            self::TYPE_EFECTIVO => 'Efectivo',
            self::TYPE_TARJETA_CREDITO => 'Tarjeta de Crédito',
            self::TYPE_VIRTUAL => 'Cuenta Virtual',
        ];
    }

    /**
     * Get the icon for each account type.
     */
    public static function getAccountTypeIcon(string $type): string
    {
        return [
            self::TYPE_CORRIENTE => 'bi-bank',
            self::TYPE_AHORROS => 'bi-piggy-bank',
            self::TYPE_NOMINA => 'bi-wallet2',
            self::TYPE_INVERSION => 'bi-graph-up-arrow',
            self::TYPE_CAJA_CHICA => 'bi-cash',
            self::TYPE_EFECTIVO => 'bi-cash-stack',
            self::TYPE_TARJETA_CREDITO => 'bi-credit-card',
            self::TYPE_VIRTUAL => 'bi-wallet',
        ][$type] ?? 'bi-question-circle';
    }

    /**
     * Get the color for each account type.
     */
    public static function getAccountTypeColor(string $type): string
    {
        return [
            self::TYPE_CORRIENTE => 'primary',
            self::TYPE_AHORROS => 'success',
            self::TYPE_NOMINA => 'info',
            self::TYPE_INVERSION => 'warning',
            self::TYPE_CAJA_CHICA => 'secondary',
            self::TYPE_EFECTIVO => 'success',
            self::TYPE_TARJETA_CREDITO => 'danger',
            self::TYPE_VIRTUAL => 'gray',
        ][$type] ?? 'gray';
    }

    // ================================================================
    // RELACIONES
    // ================================================================

    /**
     * Get the company that owns this bank account.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the bank associated with this account.
     */
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * Get the currency of this account.
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get the incomes for this bank account.
     */
    public function incomes()
    {
        return $this->hasMany(Transaction::class)->where('type', 'income');
    }

    /**
     * Get the expenses for this bank account.
     */
    public function expenses()
    {
        return $this->hasMany(Transaction::class)->where('type', 'expense');
    }

    /**
     * Get all transactions for this bank account.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // ================================================================
    // SCOPES (Filtros reutilizables)
    // ================================================================

    /**
     * Scope a query to only include active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include default accounts.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to only include accounts of a specific type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    /**
     * Scope a query to only include bank accounts (not cash or virtual).
     */
    public function scopeBankAccounts($query)
    {
        return $query->whereIn('account_type', [
            self::TYPE_CORRIENTE,
            self::TYPE_AHORROS,
            self::TYPE_NOMINA,
            self::TYPE_INVERSION,
        ]);
    }

    /**
     * Scope a query to only include cash accounts.
     */
    public function scopeCashAccounts($query)
    {
        return $query->whereIn('account_type', [
            self::TYPE_CAJA_CHICA,
            self::TYPE_EFECTIVO,
        ]);
    }

    // ================================================================
    // MÉTODOS DE NEGOCIO (Tomados del sistema actual, mejorados)
    // ================================================================

    /**
     * Obtener cuentas bancarias por empresa
     * Equivalente a getByCompany() del sistema actual
     */
    public function getByCompany(int $companyId, bool $onlyActive = true)
    {
        $query = $this->where('company_id', $companyId)
            ->with(['bank', 'currency']);

        if ($onlyActive) {
            $query->active();
        }

        return $query->orderBy('bank.name')
            ->orderBy('account_number')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'account_number' => $account->account_number,
                    'account_type' => $account->account_type,
                    'account_type_label' => $account->account_type_label,
                    'account_holder' => $account->account_holder,
                    'opening_balance' => $account->opening_balance,
                    'current_balance' => $account->current_balance,
                    'is_active' => $account->is_active,
                    'alias' => $account->alias,
                    'bank' => [
                        'id' => $account->bank?->id,
                        'name' => $account->bank?->name,
                        'code' => $account->bank?->code,
                        'country' => $account->bank?->country_name,
                    ],
                    'currency' => [
                        'id' => $account->currency?->id,
                        'code' => $account->currency?->code,
                        'symbol' => $account->currency?->symbol,
                        'name' => $account->currency?->name,
                    ],
                ];
            });
    }

    /**
     * Buscar cuenta por número
     * Equivalente a findByAccountNumber() del sistema actual
     */
    public function findByAccountNumber(string $accountNumber, int $companyId): ?self
    {
        return $this->where('account_number', $accountNumber)
            ->where('company_id', $companyId)
            ->first();
    }

    /**
     * Obtener cuenta bancaria por banco y empresa
     * Equivalente a getByBankAndCompany() del sistema actual
     */
    public function getByBankAndCompany(int $bankId, int $companyId): ?self
    {
        return $this->where('bank_id', $bankId)
            ->where('company_id', $companyId)
            ->active()
            ->first();
    }

    // ================================================================
    // MÉTODOS DE AYUDA
    // ================================================================
    /**
     * Calcular el saldo actual de la cuenta
     * Método usado por el Trait CachesBalance
     */
    public function calculateBalance(): float
    {
        $totalIncomes = $this->incomes()->sum('amount') ?? 0;
        $totalExpenses = $this->expenses()->sum('amount') ?? 0;

        return $this->opening_balance + $totalIncomes - $totalExpenses;
    }

    /**
     * Calcular el saldo en una fecha específica
     * Método usado por el Trait CachesBalance
     */
    public function calculateBalanceOnDate(string $date): float
    {
        $totalIncomes = $this->incomes()
            ->whereDate('date', '<=', $date)
            ->sum('amount') ?? 0;

        $totalExpenses = $this->expenses()
            ->whereDate('date', '<=', $date)
            ->sum('amount') ?? 0;

        return $this->opening_balance + $totalIncomes - $totalExpenses;
    }

    /**
     * Get the current balance of the account.
     * This is calculated, not stored.
     */
    public function getCurrentBalanceAttribute(): float
    {
        $totalIncomes = $this->incomes()->sum('amount') ?? 0;
        $totalExpenses = $this->expenses()->sum('amount') ?? 0;

        return $this->opening_balance + $totalIncomes - $totalExpenses;
    }

    /**
     * Get the balance on a specific date.
     */
    public function getBalanceOnDate(string $date): float
    {
        $totalIncomes = $this->incomes()
            ->whereDate('date', '<=', $date)
            ->sum('amount') ?? 0;

        $totalExpenses = $this->expenses()
            ->whereDate('date', '<=', $date)
            ->sum('amount') ?? 0;

        return $this->opening_balance + $totalIncomes - $totalExpenses;
    }

    /**
     * Check if the account has sufficient balance for a transaction.
     */
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->current_balance >= $amount;
    }

    /**
     * Get the full account name (alias + bank + account number).
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->alias} ({$this->bank?->name} - {$this->account_number})";
    }

    /**
     * Get the display name for the account type.
     */
    public function getAccountTypeLabelAttribute(): string
    {
        return self::getAccountTypes()[$this->account_type] ?? $this->account_type;
    }

    /**
     * Get the icon for the account type.
     */
    public function getAccountTypeIconAttribute(): string
    {
        return self::getAccountTypeIcon($this->account_type);
    }

    /**
     * Get the color for the account type.
     */
    public function getAccountTypeColorAttribute(): string
    {
        return self::getAccountTypeColor($this->account_type);
    }

    /**
     * Check if the account is a bank account.
     */
    public function isBankAccount(): bool
    {
        return in_array($this->account_type, [
            self::TYPE_CORRIENTE,
            self::TYPE_AHORROS,
            self::TYPE_NOMINA,
            self::TYPE_INVERSION,
        ]);
    }

    /**
     * Check if the account is a cash account.
     */
    public function isCashAccount(): bool
    {
        return in_array($this->account_type, [
            self::TYPE_CAJA_CHICA,
            self::TYPE_EFECTIVO,
        ]);
    }

    /**
     * Check if the account is active.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Get the formatted opening balance.
     */
    public function getFormattedOpeningBalanceAttribute(): string
    {
        return number_format($this->opening_balance, 2);
    }

    /**
     * Get the formatted current balance.
     */
    public function getFormattedCurrentBalanceAttribute(): string
    {
        return number_format($this->current_balance, 2);
    }

    /**
     * Get the masked account number (show only last 4 digits).
     */
    public function getMaskedAccountNumberAttribute(): string
    {
        $number = $this->account_number;
        $length = strlen($number);

        if ($length <= 4) {
            return $number;
        }

        return str_repeat('*', $length - 4) . substr($number, -4);
    }
}
