<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'companies';

    /**
     * The attributes that are mass assignable.
     * 
     * Mapeo de campos del sistema actual a Laravel:
     * - logo → logo_path
     * - subscription_plan → subscription_plan_id (FK)
     */
    protected $fillable = [
        // ================================================================
        // CAMPOS DEL SISTEMA ACTUAL
        // ================================================================
        'name',                       // ✅ Nombre comercial
        'business_name',              // ✅ Razón social
        'tax_id',                     // ✅ RIF/NIT
        'email',                      // ✅ Email corporativo
        'phone',                      // ✅ Teléfono
        'address',                    // ✅ Dirección
        'logo_path',                  // ⚠️ Renombrado: logo → logo_path
        'subscription_expires_at',    // ✅ Expiración de suscripción
        'max_users',                  // ✅ Límite de usuarios (legado)
        'max_accounts',               // ✅ Límite de cuentas (legado)
        'max_transactions_per_month', // ✅ Límite de transacciones (legado)
        'is_active',                  // ✅ Estado

        // ================================================================
        // NUEVOS CAMPOS (mejoras)
        // ================================================================
        'timezone',                   // ✅ Zona horaria
        'created_by',                 // ✅ Auditoría: quién creó

        // ================================================================
        // RELACIONES (FK)
        // ================================================================
        'subscription_plan_id',       // ⚠️ Cambio: subscription_plan (ENUM) → subscription_plan_id (FK)
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'subscription_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'max_users' => 'integer',
        'max_accounts' => 'integer',
        'max_transactions_per_month' => 'integer',
        'subscription_plan_id' => 'integer',
        'created_by' => 'integer',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // ================================================================
    // RELACIONES
    // ================================================================

    /**
     * Get the subscription plan associated with this company.
     */
    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /**
     * Get the user who created this company.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the users that belong to this company.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->withPivot('is_default', 'joined_at', 'left_at')
            ->withTimestamps();
    }

    /**
     * Get the bank accounts associated with this company.
     */
    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class);
    }

    /**
     * Get the transactions associated with this company.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the audit logs associated with this company.
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Get the import sessions associated with this company.
     */
    public function importSessions()
    {
        return $this->hasMany(ImportSession::class);
    }

    // ================================================================
    // SCOPES (Filtros reutilizables)
    // ================================================================

    /**
     * Scope a query to only include active companies.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to find a company by tax_id.
     */
    public function scopeByTaxId($query, string $taxId)
    {
        return $query->where('tax_id', $taxId);
    }

    /**
     * Scope a query to get companies with expiring subscriptions.
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('subscription_expires_at', '<=', now()->addDays($days))
            ->where('subscription_expires_at', '>', now());
    }

    /**
     * Scope a query to get companies with expired subscriptions.
     */
    public function scopeExpired($query)
    {
        return $query->where('subscription_expires_at', '<', now());
    }

    // ================================================================
    // MÉTODOS DE NEGOCIO (Tomados del sistema actual, mejorados)
    // ================================================================

    /**
     * Buscar empresa por NIT/RUC
     * Equivalente a findByTaxId() del sistema actual
     */
    public function findByTaxId(string $taxId): ?self
    {
        return $this->byTaxId($taxId)->first();
    }

    /**
     * Contar empresas activas
     * Equivalente a countActive() del sistema actual
     */
    public function countActive(): int
    {
        return $this->active()->count();
    }

    /**
     * Obtener empresas activas para el dashboard público
     * Equivalente a getActiveCompanies() del sistema actual
     */
    public function getActiveCompanies()
    {
        return $this->active()
            ->select('id', 'name', 'business_name', 'tax_id', 'email', 'phone', 'is_active')
            ->orderBy('name')
            ->get();
    }

    /**
     * Obtener empresas con suscripción activa
     */
    public function getActiveSubscriptionCompanies()
    {
        return $this->active()
            ->where(function ($query) {
                $query->whereNull('subscription_expires_at')
                    ->orWhere('subscription_expires_at', '>', now());
            })
            ->get();
    }

    // ================================================================
    // MÉTODOS DE AYUDA
    // ================================================================

    /**
     * Check if the company is active.
     */
    public function isActive(): bool
    {
        return $this->is_active && ($this->subscription_expires_at === null || $this->subscription_expires_at->isFuture());
    }

    /**
     * Check if the company has a specific feature.
     */
    public function hasFeature(string $feature): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        return $this->subscriptionPlan?->hasFeature($feature) ?? false;
    }

    /**
     * Check if the company can add a new user.
     */
    public function canAddUser(): bool
    {
        $currentUsers = $this->users()->count();
        $maxUsers = $this->subscriptionPlan?->max_users ?? $this->max_users ?? 0;

        return $currentUsers < $maxUsers;
    }

    /**
     * Check if the company can add a new bank account.
     */
    public function canAddBankAccount(): bool
    {
        $currentAccounts = $this->bankAccounts()->count();
        $maxAccounts = $this->subscriptionPlan?->max_bank_accounts ?? $this->max_accounts ?? 0;

        return $currentAccounts < $maxAccounts;
    }

    /**
     * Check if the company can add a new transaction.
     */
    public function canAddTransaction(): bool
    {
        $currentTransactions = $this->transactions()
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->count();

        $maxTransactions = $this->subscriptionPlan?->max_transactions_per_month ?? $this->max_transactions_per_month ?? 0;

        return $currentTransactions < $maxTransactions;
    }

    /**
     * Get the total balance across all bank accounts.
     */
    public function getTotalBalanceAttribute(): float
    {
        return $this->bankAccounts->sum(function ($account) {
            return $account->calculateBalance();
        });
    }

    /**
     * Get the formatted logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return asset('storage/' . $this->logo_path);
    }

    /**
     * Get the subscription plan name.
     */
    public function getSubscriptionPlanNameAttribute(): string
    {
        return $this->subscriptionPlan?->name ?? 'Sin plan';
    }

    /**
     * Get the company display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->business_name ?? $this->name;
    }

    // ================================================================
    // OBSERVERS (Auditoría)
    // ================================================================

    protected static function booted()
    {
        static::creating(function ($company) {
            // ✅ Opción 1: Usar guard específico
            if ($user = auth()->guard('sanctum')->user()) {
                $company->created_by = $user->id;
            }
            // Si no funciona con sanctum, probar con web
            else if ($user = auth()->guard('web')->user()) {
                $company->created_by = $user->id;
            }
        });
    }
}
