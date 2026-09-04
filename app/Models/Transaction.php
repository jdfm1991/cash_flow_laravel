<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Transaction extends Model
{
    use SoftDeletes;

    protected $table = 'transactions';

    protected $fillable = [
        'company_id',
        'user_id',
        'bank_account_id',
        'account_id',
        'category_id',
        'type',
        'amount',
        'currency_id',
        'exchange_rate_id',
        'exchange_rate',
        'amount_converted',
        'date',
        'description',
        'reference',
        'payment_method',
        'receipt_path',
        'transfer_id',
        'hash',
        'is_reconciled',
        'reconciled_at',
        'reconciled_by',
        'asiento_contable_id',
        'contabilizada',
        'import_session_id',
        'source',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'amount_converted' => 'decimal:4',
        'exchange_rate' => 'decimal:8',
        'date' => 'date',
        'is_reconciled' => 'boolean',
        'contabilizada' => 'boolean',
        'reconciled_at' => 'datetime',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ================================================================
    // TIPOS Y MÉTODOS DE PAGO
    // ================================================================

    const TYPE_INCOME = 'income';
    const TYPE_EXPENSE = 'expense';
    const TYPE_TRANSFER = 'transfer';

    const PAYMENT_CASH = 'cash';
    const PAYMENT_BANK = 'bank';
    const PAYMENT_TRANSFER = 'transfer';

    const SOURCE_MANUAL = 'manual';
    const SOURCE_EXCEL = 'excel';
    const SOURCE_MIGRATION = 'migration';
    const SOURCE_API = 'api';

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
    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
    public function exchangeRate()
    {
        return $this->belongsTo(ExchangeRate::class);
    }
    public function reconciler()
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function transfer()
    {
        return $this->belongsTo(Transaction::class, 'transfer_id');
    }

    public function linkedTransfer()
    {
        return $this->hasOne(Transaction::class, 'transfer_id', 'id');
    }

    // ================================================================
    // SCOPES (Filtros reutilizables)
    // ================================================================

    public function scopeIncome($query)
    {
        return $query->where('type', self::TYPE_INCOME);
    }
    public function scopeExpense($query)
    {
        return $query->where('type', self::TYPE_EXPENSE);
    }
    public function scopeTransfer($query)
    {
        return $query->where('type', self::TYPE_TRANSFER);
    }
    public function scopeByDate($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }
    public function scopeReconciled($query)
    {
        return $query->where('is_reconciled', true);
    }
    public function scopeUnreconciled($query)
    {
        return $query->where('is_reconciled', false);
    }
    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
    public function scopeByAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }
    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }
    public function scopeByBankAccount($query, int $bankAccountId)
    {
        return $query->where('bank_account_id', $bankAccountId);
    }

    // ================================================================
    // MÉTODOS DE NEGOCIO (Tomados del sistema actual)
    // ================================================================

    protected static function booted()
    {
        static::creating(function ($transaction) {
            if (auth()->check()) {
                $transaction->user_id = auth()->id();
            }

            // ✅ Calcular amount
            $transaction->amount_converted = $transaction->calculateBaseCurrencyAmount();

            // ✅ Generar hash
            $transaction->hash = md5(
                $transaction->company_id .
                    $transaction->amount .
                    $transaction->date .
                    $transaction->type .
                    ($transaction->reference ?? '') .
                    ($transaction->description ?? '')
            );
        });
    }

    // ================================================================
    // MÉTODOS DE CÁLCULO
    // ================================================================

    /**
     * Calcular el monto en moneda base
     */
    public function calculateBaseCurrencyAmount(): float
    {
        // Si el campo ya tiene valor, usarlo
        if ($this->amount_converted  && $this->amount_converted  > 0) {
            return $this->amount_converted ;
        }

        try {
            $currencyService = app(\App\Services\CurrencyService::class);
            $baseCurrency = $currencyService->getBaseCurrency();

            if (!$baseCurrency) {
                Log::warning('No se encontró moneda base', [
                    'transaction' => $this->toArray()
                ]);
                return $this->amount;
            }

            // Si la moneda es la base, el monto es el mismo
            if ($this->currency_id == $baseCurrency->id) {
                $this->exchange_rate = 1.0;
                return $this->amount;
            }

            // Obtener tasa de cambio
            $rate = $currencyService->getRate(
                $this->currency_id,
                $baseCurrency->id,
                $this->date ?? now()->toDateString()
            );

            $this->exchange_rate = $rate;
            return round($this->amount * $rate, 4);
        } catch (\Exception $e) {
            Log::error("Error calculando amount_converted", [
                'transaction' => $this->toArray(),
                'error' => $e->getMessage()
            ]);
            return $this->amount;
        }
    }

    /**
     * Obtener transacciones con detalles de cuenta
     * Equivalente a getWithAccount() del sistema actual
     */
    public function getWithAccount(int $companyId, array $filters = [], ?int $limit = null)
    {
        $query = $this->with(['account', 'category', 'bankAccount'])
            ->where('company_id', $companyId);

        if (!empty($filters['start_date'])) {
            $query->where('date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('date', '<=', $filters['end_date']);
        }

        if (!empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('description', 'LIKE', "%{$filters['search']}%")
                    ->orWhere('reference', 'LIKE', "%{$filters['search']}%");
            });
        }

        $query->orderBy('date', 'desc')->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Obtener total por período
     * Equivalente a getTotalByPeriod() del sistema actual
     */
    public function getTotalByPeriod(int $companyId, string $startDate, string $endDate): float
    {
        return (float) $this->where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount_converted');
    }

    /**
     * Obtener total por cuenta
     * Equivalente a getTotalByAccount() del sistema actual
     */
    public function getTotalByAccount(int $companyId, int $accountId, string $startDate, string $endDate): float
    {
        return (float) $this->where('company_id', $companyId)
            ->where('account_id', $accountId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount_converted');
    }

    /**
     * Obtener total por categoría
     * Equivalente a getTotalByCategory() del sistema actual
     */
    public function getTotalByCategory(int $companyId, int $categoryId, string $startDate, string $endDate): float
    {
        return (float) $this->where('company_id', $companyId)
            ->where('category_id', $categoryId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount_converted');
    }

    /**
     * Contar transacciones por cuenta
     * Equivalente a countByAccount() del sistema actual
     */
    public function countByAccount(int $accountId): int
    {
        return $this->where('account_id', $accountId)->count();
    }

    /**
     * Resumen mensual
     * Equivalente a getMonthlySummary() del sistema actual
     */
    public function getMonthlySummary(int $companyId, int $year): array
    {
        $results = $this->select(
            DB::raw('MONTH(date) as month'),
            DB::raw('SUM(amount_converted) as total'),
            DB::raw('COUNT(*) as count')
        )
            ->where('company_id', $companyId)
            ->whereYear('date', $year)
            ->groupBy(DB::raw('MONTH(date)'))
            ->orderBy(DB::raw('MONTH(date)'))
            ->get();

        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyData[$i] = ['month' => $i, 'total' => 0, 'count' => 0];
        }

        foreach ($results as $row) {
            $monthlyData[(int) $row->month] = [
                'month' => (int) $row->month,
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ];
        }

        return array_values($monthlyData);
    }

    /**
     * Verificar que la transacción pertenece a una empresa
     * Equivalente a belongsToCompany() del sistema actual
     */
    public function belongsToCompany(int $transactionId, int $companyId): bool
    {
        return $this->where('id', $transactionId)
            ->where('company_id', $companyId)
            ->exists();
    }

    /**
     * Contar transacciones por cuenta bancaria
     * Equivalente a countByBankAccount() del sistema actual
     */
    public function countByBankAccount(int $bankAccountId): int
    {
        return $this->where('bank_account_id', $bankAccountId)->count();
    }

    /**
     * Obtener transacciones por cuenta bancaria
     * Equivalente a getByBankAccount() del sistema actual
     */
    public function getByBankAccount(int $companyId, int $bankAccountId, array $filters = [], ?int $limit = null)
    {
        $query = $this->with(['account', 'category'])
            ->where('company_id', $companyId)
            ->where('bank_account_id', $bankAccountId);

        if (!empty($filters['start_date'])) {
            $query->where('date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('date', '<=', $filters['end_date']);
        }

        if (!empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        $query->orderBy('date', 'desc')->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Total por cuenta bancaria
     * Equivalente a getTotalByBankAccount() del sistema actual
     */
    public function getTotalByBankAccount(int $companyId, int $bankAccountId, string $startDate, string $endDate): float
    {
        return (float) $this->where('company_id', $companyId)
            ->where('bank_account_id', $bankAccountId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount_converted');
    }

    /**
     * Contar por moneda
     * Equivalente a countByCurrency() del sistema actual
     */
    public function countByCurrency(int $currencyId): int
    {
        return $this->where('currency_id', $currencyId)->count();
    }

    /**
     * Total por empresa
     * Equivalente a getTotalByCompany() del sistema actual
     */
    public function getTotalByCompany(int $companyId, string $startDate, string $endDate): float
    {
        return (float) $this->where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount_converted');
    }

    /**
     * Transacciones recientes por empresa
     * Equivalente a getRecentByCompany() del sistema actual
     */
    public function getRecentByCompany(int $companyId, int $limit)
    {
        return $this->with(['account', 'category', 'bankAccount'])
            ->where('company_id', $companyId)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Distribución por categorías
     * Equivalente a getCategoryDistributionByCompany() del sistema actual
     */
    public function getCategoryDistributionByCompany(int $companyId, string $startDate, string $endDate): array
    {
        $results = $this->select(
            'categories.id as category_id',
            'categories.name as category_name',
            'categories.color',
            'categories.icon',
            DB::raw('SUM(transactions.amount_converted) as total')
        )
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.company_id', $companyId)
            ->whereBetween('transactions.date', [$startDate, $endDate])
            ->groupBy('categories.id', 'categories.name', 'categories.color', 'categories.icon')
            ->orderBy('total', 'desc')
            ->get();

        $total = $results->sum('total');
        $distribution = [];

        foreach ($results as $row) {
            $distribution[] = [
                'category_id' => $row->category_id,
                'name' => $row->category_name,
                'color' => $row->color ?? '#6c757d',
                'icon' => $row->icon ?? 'bi-tag',
                'total' => (float) $row->total,
                'percentage' => $total > 0 ? round(($row->total / $total) * 100, 2) : 0,
            ];
        }

        return $distribution;
    }

    /**
     * Primera fecha de transacción
     * Equivalente a getFirstDateByCompany() del sistema actual
     */
    public function getFirstDateByCompany(int $companyId): ?string
    {
        return $this->where('company_id', $companyId)
            ->orderBy('date', 'asc')
            ->value('date');
    }

    /**
     * Última fecha de transacción
     * Equivalente a getLastDateByCompany() del sistema actual
     */
    public function getLastDateByCompany(int $companyId): ?string
    {
        return $this->where('company_id', $companyId)
            ->orderBy('date', 'desc')
            ->value('date');
    }

    /**
     * Todas las transacciones (super_admin)
     * Equivalente a getAllGlobal() del sistema actual
     */
    public function getAllGlobal()
    {
        return $this->with(['company', 'account', 'category', 'bankAccount'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Transacciones por empresa (usuario normal)
     * Equivalente a getByCompany() del sistema actual
     */
    public function getByCompany(int $companyId)
    {
        return $this->with(['account', 'category', 'bankAccount'])
            ->where('company_id', $companyId)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Estadísticas de transacciones
     * Equivalente a getStats() del sistema actual
     */
    public function getStats(?int $companyId, ?string $startDate, ?string $endDate): array
    {
        $query = $this->select(
            DB::raw('COUNT(*) as count'),
            DB::raw('COALESCE(SUM(amount_converted), 0) as total'),
            DB::raw('COUNT(DISTINCT currency_id) as currencies_count')
        );

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $result = $query->first();

        return [
            'count' => (int) ($result->count ?? 0),
            'total' => (float) ($result->total ?? 0),
            'currencies_count' => (int) ($result->currencies_count ?? 0),
        ];
    }

    // ================================================================
    // MÉTODOS DE AYUDA (Laravel)
    // ================================================================

    public function isIncome(): bool
    {
        return $this->type === self::TYPE_INCOME;
    }
    public function isExpense(): bool
    {
        return $this->type === self::TYPE_EXPENSE;
    }
    public function isTransfer(): bool
    {
        return $this->type === self::TYPE_TRANSFER;
    }

    public function getSignAttribute(): int
    {
        return $this->isIncome() ? 1 : -1;
    }

    public function getSignedAmountAttribute(): float
    {
        return $this->amount * $this->sign;
    }

    public function getTypeLabelAttribute(): string
    {
        return [
            self::TYPE_INCOME => 'Ingreso',
            self::TYPE_EXPENSE => 'Egreso',
            self::TYPE_TRANSFER => 'Transferencia',
        ][$this->type] ?? $this->type;
    }
}
