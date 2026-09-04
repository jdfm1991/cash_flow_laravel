<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'accounts';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'category_id',
        'name',
        'description',
        'codigo_contable',
        'cuenta_contable_id',
        'is_system',
        'is_active',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'category_id' => 'integer',
        'cuenta_contable_id' => 'integer',
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
    // RELACIONES
    // ================================================================

    /**
     * Get the category that this account belongs to.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the transactions associated with this account.
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
     * Scope a query to only include system accounts.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope a query to order by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope a query to get accounts by category type.
     */
    public function scopeByCategoryType($query, string $type)
    {
        return $query->whereHas('category', function ($q) use ($type) {
            $q->where('type', $type);
        });
    }

    /**
     * Scope a query to get income accounts.
     * Equivalente a getByType('income') del sistema actual
     */
    public function scopeIncome($query)
    {
        return $this->scopeByCategoryType($query, 'income');
    }

    /**
     * Scope a query to get expense accounts.
     * Equivalente a getByType('expense') del sistema actual
     */
    public function scopeExpense($query)
    {
        return $this->scopeByCategoryType($query, 'expense');
    }

    // ================================================================
    // MÉTODOS DE NEGOCIO (Tomados del sistema actual, mejorados)
    // ================================================================

    /**
     * Obtener cuentas globales (catálogo)
     * Equivalente a getGlobalAccounts() del sistema actual
     */
    public function getGlobalAccounts(?string $type = null)
    {
        $query = $this->active()->ordered();

        if ($type && in_array($type, ['income', 'expense'])) {
            $query->byCategoryType($type);
        }

        return $query->get();
    }

    /**
     * Obtener cuentas por tipo
     * Equivalente a getByType() del sistema actual
     */
    public function getByType(string $type)
    {
        return $this->byCategoryType($type)->active()->ordered()->get();
    }

    /**
     * Contar cuentas por categoría (por nombre)
     * Equivalente a countByCategory() del sistema actual
     */
    public function countByCategory(string $categoryName, string $type): int
    {
        return $this->whereHas('category', function ($q) use ($categoryName, $type) {
            $q->where('name', $categoryName)->where('type', $type);
        })->count();
    }

    /**
     * Contar cuentas por categoría (por ID)
     */
    public function countByCategoryId(int $categoryId): int
    {
        return $this->where('category_id', $categoryId)->count();
    }

    /**
     * Obtener distribución de transacciones por categoría
     * Equivalente a getCategoryDistribution() del sistema actual
     * NOTA: Este método se moverá a ReportService, pero lo mantenemos aquí por compatibilidad
     */
    public function getCategoryDistribution(int $companyId, string $type, string $startDate, string $endDate): array
    {
        $categories = $this->whereHas('category', function ($q) use ($type) {
            $q->where('type', $type)->where('is_active', true);
        })
        ->with(['category', 'transactions' => function ($q) use ($companyId, $startDate, $endDate) {
            $q->where('company_id', $companyId)
                ->whereBetween('date', [$startDate, $endDate]);
        }])
        ->get();

        $result = [];
        foreach ($categories as $account) {
            foreach ($account->transactions as $transaction) {
                $catId = $account->category_id;
                if (!isset($result[$catId])) {
                    $result[$catId] = [
                        'category_id' => $catId,
                        'name' => $account->category->name,
                        'color' => $account->category->color ?? '#6c757d',
                        'icon' => $account->category->icon ?? 'bi-tag',
                        'total' => 0,
                    ];
                }
                $result[$catId]['total'] += $transaction->amount;
            }
        }

        // Calcular porcentajes
        $total = array_sum(array_column($result, 'total'));
        foreach ($result as &$item) {
            $item['percentage'] = $total > 0 ? round(($item['total'] / $total) * 100, 2) : 0;
        }

        return array_values($result);
    }

    // ================================================================
    // MÉTODOS DE AYUDA
    // ================================================================

    /**
     * Get the account type through its category.
     */
    public function getTypeAttribute(): ?string
    {
        return $this->category?->type;
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return $this->category?->type_label ?? 'Desconocido';
    }

    /**
     * Get the full name with category.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->category?->name})";
    }

    /**
     * Get the display name with type badge.
     */
    public function getDisplayNameAttribute(): string
    {
        $type = $this->type_label;
        return "{$this->name} [{$type}]";
    }

    /**
     * Check if this is an income account.
     */
    public function isIncome(): bool
    {
        return $this->category?->type === 'income';
    }

    /**
     * Check if this is an expense account.
     */
    public function isExpense(): bool
    {
        return $this->category?->type === 'expense';
    }

    /**
     * Check if this account is active.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Check if this is a system account.
     */
    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }
}