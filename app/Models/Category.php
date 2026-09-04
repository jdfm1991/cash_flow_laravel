<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'categories';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        // ================================================================
        // CAMPOS DEL SISTEMA ACTUAL
        // ================================================================
        'name',            // ✅ Nombre de la categoría
        'type',            // ✅ income / expense
        'icon',            // ✅ Icono
        'color',           // ✅ Color
        'description',     // ✅ Descripción
        'is_system',       // ✅ Categoría del sistema (no editable)
        'is_active',       // ✅ Activa/Inactiva
        'sort_order',      // ✅ Orden de visualización

        // ================================================================
        // NUEVOS CAMPOS (mejoras)
        // ================================================================
        'parent_id',       // ✅ Jerarquía (categoría padre)
        'code',            // ✅ Código contable (ej: 4.1.1)
        'codigo_contable', // ✅ Código en sistema contable externo
        'cuenta_contable_id', // ✅ ID en sistema contable externo
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'parent_id' => 'integer',
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
    // TIPOS DE CATEGORÍA
    // ================================================================

    const TYPE_INCOME = 'income';
    const TYPE_EXPENSE = 'expense';
    const TYPE_ASSET = 'asset';
    const TYPE_LIABILITY = 'liability';
    const TYPE_EQUITY = 'equity';

    /**
     * Get all category types with labels.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_INCOME => 'Ingreso',
            self::TYPE_EXPENSE => 'Egreso',
            self::TYPE_ASSET => 'Activo',
            self::TYPE_LIABILITY => 'Pasivo',
            self::TYPE_EQUITY => 'Patrimonio',
        ];
    }

    /**
     * Get the color for each category type.
     */
    public static function getTypeColor(string $type): string
    {
        return [
            self::TYPE_INCOME => 'success',
            self::TYPE_EXPENSE => 'danger',
            self::TYPE_ASSET => 'primary',
            self::TYPE_LIABILITY => 'warning',
            self::TYPE_EQUITY => 'info',
        ][$type] ?? 'gray';
    }

    // ================================================================
    // RELACIONES
    // ================================================================

    /**
     * Get the parent category.
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get all descendants (recursive).
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get the accounts associated with this category.
     */
    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Get the transactions associated with this category.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // ================================================================
    // SCOPES (Filtros reutilizables)
    // ================================================================

    /**
     * Scope a query to only include root categories (no parent).
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope a query to only include active categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include categories of a specific type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include system categories.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope a query to only include income categories.
     */
    public function scopeIncome($query)
    {
        return $query->where('type', self::TYPE_INCOME);
    }

    /**
     * Scope a query to only include expense categories.
     */
    public function scopeExpense($query)
    {
        return $query->where('type', self::TYPE_EXPENSE);
    }

    /**
     * Scope a query to order by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ================================================================
    // MÉTODOS DE NEGOCIO (Tomados del sistema actual, mejorados)
    // ================================================================

    /**
     * Obtener categorías por tipo
     * Equivalente a getByType() del sistema actual
     */
    public function getByType(string $type, bool $onlyActive = true)
    {
        $query = $this->byType($type);

        if ($onlyActive) {
            $query->active();
        }

        return $query->ordered()->get();
    }

    /**
     * Obtener todas las categorías activas
     * Equivalente a getAllActive() del sistema actual
     */
    public function getAllActive()
    {
        return $this->active()
            ->ordered()
            ->get();
    }

    /**
     * Verificar si una categoría tiene cuentas asociadas
     * Equivalente a hasAccounts() del sistema actual
     */
    public function hasAccounts(int $categoryId): bool
    {
        return $this->find($categoryId)?->accounts()->exists() ?? false;
    }

    /**
     * Verificar si es categoría del sistema (no editable/eliminable)
     * Equivalente a isSystemCategory() del sistema actual
     */
    public function isSystemCategory(int $id): bool
    {
        return $this->find($id)?->is_system ?? false;
    }

    /**
     * Obtener todas las categorías (sin filtros)
     * Equivalente a getAll() del sistema actual
     */
    public function getAll()
    {
        return $this->ordered()->get();
    }

    /**
     * Buscar categoría por nombre y tipo
     * Equivalente a findByNameAndType() del sistema actual
     */
    public function findByNameAndType(string $name, string $type): ?self
    {
        return $this->where('name', $name)
            ->where('type', $type)
            ->first();
    }

    /**
     * Obtener las cuentas que usan una categoría
     * Equivalente a getAccountsUsingCategory() del sistema actual
     */
    public function getAccountsUsingCategory(int $categoryId)
    {
        return $this->find($categoryId)?->accounts ?? collect();
    }

    /**
     * Obtener todas las categorías con sus colores (para UI)
     * Equivalente a getAllWithColors() del sistema actual
     */
    public function getAllWithColors()
    {
        return $this->active()
            ->select('id', 'name', 'type', 'color', 'icon', 'is_active')
            ->ordered()
            ->get();
    }

    // ================================================================
    // MÉTODOS DE AYUDA
    // ================================================================

    /**
     * Get the full path of the category (e.g., "Ingresos > Servicios > Consultas").
     */
    public function getPathAttribute(): string
    {
        $path = [$this->name];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $path);
    }

    /**
     * Get the full code path (e.g., "4 > 4.1 > 4.1.1").
     */
    public function getCodePathAttribute(): string
    {
        $codes = [$this->code ?? ''];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($codes, $parent->code ?? '');
            $parent = $parent->parent;
        }

        return implode(' > ', array_filter($codes));
    }

    /**
     * Get the depth level of the category (0 = root).
     */
    public function getLevelAttribute(): int
    {
        $level = 0;
        $parent = $this->parent;

        while ($parent) {
            $level++;
            $parent = $parent->parent;
        }

        return $level;
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    /**
     * Get the type color.
     */
    public function getTypeColorAttribute(): string
    {
        return self::getTypeColor($this->type);
    }

    /**
     * Check if this is a root category.
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if this category has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->count() > 0;
    }

    /**
     * Check if this category is active.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Check if this category is a system category.
     */
    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }

    /**
     * Get all children IDs recursively.
     */
    public function getDescendantIds(): array
    {
        $ids = [];
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getDescendantIds());
        }
        return $ids;
    }

    /**
     * Get the full display name with path.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->path . ' (' . $this->type_label . ')';
    }

    /**
     * Get the icon with color (for UI).
     */
    public function getIconWithColorAttribute(): string
    {
        return "<i class='{$this->icon}' style='color: {$this->color};'></i>";
    }

    /**
     * Obtener todas las categorías con sus colores (para UI)
     * Equivalente a getCategoriesWithColors() del sistema actual
     */
    public function getCategoriesWithColors()
    {
        return $this->active()
            ->select('id', 'name', 'type', 'color', 'icon', 'is_active')
            ->ordered()
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type,
                    'color' => $category->color ?? '#6c757d',
                    'icon' => $category->icon ?? 'bi-tag',
                    'is_active' => $category->is_active,
                    'type_label' => $category->type_label,
                ];
            });
    }
}
