<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    /**
     * La tabla asociada al modelo.
     */
    protected $table = 'audit_logs';

    /**
     * Desactivar timestamps (usamos created_at manual).
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'company_id',
        'action',
        'entity_type',
        'entity_id',
        'old_data',
        'new_data',
        'metadata',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'metadata' => 'array',
        'entity_id' => 'integer',
        'user_id' => 'integer',
        'company_id' => 'integer',
        'created_at' => 'datetime',
    ];

    // ================================================================
    // ACCIONES PREDEFINIDAS
    // ================================================================

    const ACTION_LOGIN = 'login';
    const ACTION_LOGOUT = 'logout';
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_RESTORE = 'restore';
    const ACTION_VIEW = 'view';
    const ACTION_EXPORT = 'export';
    const ACTION_IMPORT = 'import';
    const ACTION_SWITCH_COMPANY = 'switch_company';
    const ACTION_LOGIN_FAILED = 'login_failed';
    const ACTION_PASSWORD_RESET = 'password_reset';

    /**
     * Get all actions with labels.
     */
    public static function getActions(): array
    {
        return [
            self::ACTION_LOGIN => 'Inicio de sesión',
            self::ACTION_LOGOUT => 'Cierre de sesión',
            self::ACTION_CREATE => 'Creación',
            self::ACTION_UPDATE => 'Actualización',
            self::ACTION_DELETE => 'Eliminación',
            self::ACTION_RESTORE => 'Restauración',
            self::ACTION_VIEW => 'Visualización',
            self::ACTION_EXPORT => 'Exportación',
            self::ACTION_IMPORT => 'Importación',
            self::ACTION_SWITCH_COMPANY => 'Cambio de empresa',
            self::ACTION_LOGIN_FAILED => 'Intento de login fallido',
            self::ACTION_PASSWORD_RESET => 'Recuperación de contraseña',
        ];
    }

    // ================================================================
    // RELACIONES
    // ================================================================

    /**
     * Get the user who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company context.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // ================================================================
    // SCOPES
    // ================================================================

    /**
     * Scope a query to get logs for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to get logs for a specific company.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query to get logs for a specific action.
     */
    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to get logs for a specific entity.
     */
    public function scopeForEntity($query, string $type, int $id)
    {
        return $query->where('entity_type', $type)
            ->where('entity_id', $id);
    }

    /**
     * Scope a query to get logs from a specific date range.
     */
    public function scopeBetweenDates($query, string $start, string $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Scope a query to get recent logs.
     */
    public function scopeRecent($query, int $limit = 100)
    {
        return $query->orderBy('created_at', 'desc')
            ->limit($limit);
    }

    // ================================================================
    // MÉTODOS DE AYUDA
    // ================================================================

    /**
     * Get the action label.
     */
    public function getActionLabelAttribute(): string
    {
        return self::getActions()[$this->action] ?? $this->action;
    }

    /**
     * Get the entity type label.
     */
    public function getEntityTypeLabelAttribute(): string
    {
        $labels = [
            'User' => 'Usuario',
            'Company' => 'Empresa',
            'Bank' => 'Banco',
            'BankAccount' => 'Cuenta Bancaria',
            'Currency' => 'Moneda',
            'ExchangeRate' => 'Tasa de Cambio',
            'Category' => 'Categoría',
            'Account' => 'Cuenta Contable',
            'Transaction' => 'Transacción',
            'SubscriptionPlan' => 'Plan de Suscripción',
        ];

        return $labels[$this->entity_type] ?? $this->entity_type;
    }

    /**
     * Get the entity URL if available.
     */
    public function getEntityUrlAttribute(): ?string
    {
        // Esto puede variar según la entidad
        $routes = [
            'User' => route('admin.users.edit', $this->entity_id),
            'Company' => route('admin.companies.edit', $this->entity_id),
            'Transaction' => route('admin.transactions.edit', $this->entity_id),
        ];

        return $routes[$this->entity_type] ?? null;
    }

    /**
     * Check if the log is for a user action.
     */
    public function isUserAction(): bool
    {
        return in_array($this->action, [
            self::ACTION_LOGIN,
            self::ACTION_LOGOUT,
            self::ACTION_LOGIN_FAILED,
            self::ACTION_PASSWORD_RESET,
        ]);
    }

    /**
     * Check if the log is for a CRUD action.
     */
    public function isCrudAction(): bool
    {
        return in_array($this->action, [
            self::ACTION_CREATE,
            self::ACTION_UPDATE,
            self::ACTION_DELETE,
            self::ACTION_RESTORE,
        ]);
    }

    /**
     * Get a human-readable summary of the changes.
     */
    public function getChangeSummaryAttribute(): string
    {
        if ($this->action === self::ACTION_CREATE) {
            return "Creó un nuevo {$this->entity_type_label}";
        }

        if ($this->action === self::ACTION_UPDATE) {
            return "Actualizó un {$this->entity_type_label}";
        }

        if ($this->action === self::ACTION_DELETE) {
            return "Eliminó un {$this->entity_type_label}";
        }

        return $this->action_label;
    }

    /**
     * Get the entity display name.
     */
    public function getEntityDisplayAttribute(): string
    {
        $entity = null;

        switch ($this->entity_type) {
            case 'User':
                $entity = User::withTrashed()->find($this->entity_id);
                return $entity ? $entity->name : 'Usuario eliminado';
            case 'Company':
                $entity = Company::withTrashed()->find($this->entity_id);
                return $entity ? $entity->name : 'Empresa eliminada';
            case 'Transaction':
                $entity = Transaction::withTrashed()->find($this->entity_id);
                return $entity ? "Transacción #{$entity->id}" : 'Transacción eliminada';
            default:
                return "#{$this->entity_id}";
        }
    }
}