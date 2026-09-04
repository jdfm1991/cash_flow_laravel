<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, SoftDeletes;

    protected $guard_name = 'web'; // Guard personalizado

    // ✅ Usar nuestro modelo extendido
    protected $roleModel = Role::class;
    protected $permissionModel = Permission::class;

    /**
     * The attributes that are mass assignable.
     * 
     * Mapeo de campos del sistema actual a Laravel:
     * - company_id → current_company_id (contexto multiempresa)
     * - password_hash → password (Laravel standard)
     */
    protected $fillable = [
        // ================================================================
        // CAMPOS DEL SISTEMA ACTUAL (con nombres Laravel)
        // ================================================================
        'username',              // ✅ Mantener
        'email',                 // ✅ Mantener
        'password',              // ⚠️ Cambio: password_hash → password
        'name',             // ✅ Mantener
        'avatar',                // ✅ Mantener
        'is_active',             // ✅ Mantener
        'email_verified',        // ✅ Mantener (compatibilidad)
        'last_login_at',            // ✅ Mantener
        'failed_login_attempts', // ✅ Mantener
        'locked_until',          // ✅ Mantener

        // ================================================================
        // NUEVOS CAMPOS (mejoras del sistema)
        // ================================================================
        'last_login_ip',         // ✅ Nuevo: auditoría
        'last_login_user_agent', // ✅ Nuevo: auditoría
        'current_company_id',    // ✅ Nuevo: contexto multiempresa

        // ================================================================
        // CAMPOS DE SISTEMA (Laravel)
        // ================================================================
        'remember_token',
        'email_verified_at',     // ✅ Laravel standard
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        // ================================================================
        // CASTS DEL SISTEMA ACTUAL
        // ================================================================
        'is_active' => 'boolean',
        'email_verified' => 'boolean',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'failed_login_attempts' => 'integer',

        // ================================================================
        // CASTS DE LARAVEL
        // ================================================================
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'current_company_id' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'name_display',
    ];

    /**
     * ✅ Sobrescribir el método getGuardName para asegurar que siempre sea 'web'
     */
    public function getGuardName(): string
    {
        return 'web';
    }

    /**
     * ✅ Método para verificar permisos con guard específico
     */
    public function can($ability, $arguments = []): bool
    {
        if (is_string($ability)) {
            return $this->hasPermissionTo($ability, 'web');
        }
        return parent::can($ability, $arguments);
    }


    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // ✅ Asegurar que el usuario siempre tenga el guard_name correcto
        static::retrieved(function ($user) {
            if ($user->guard_name !== 'web') {
                $user->setGuardName('web');
            }
        });

        static::creating(function ($user) {
            $user->setGuardName('web');
        });

        static::updating(function ($user) {
            if ($user->guard_name !== 'web') {
                $user->setGuardName('web');
            }
        });
    }

    /**
     * ✅ Forzar la verificación de permisos con guard 'web'
     */
    public function canWithGuard(string $permission): bool
    {
        return $this->hasPermissionTo($permission, 'web');
    }

    // ================================================================
    // MUTATORS (para compatibilidad con sistema actual)
    // ================================================================

    /**
     * Get the user's full name (compatibilidad con sistema actual).
     */
    public function getFullNameDisplayAttribute(): string
    {
        return $this->name ?? $this->username ?? 'Usuario';
    }

    /**
     * Set the user's password (hashea automáticamente).
     * ✅ CORREGIDO: Solo actualiza si hay un valor válido
     */
    public function setPasswordAttribute($value): void
    {
        // ✅ Si el valor está vacío o es null, NO actualizar
        if (empty($value)) {
            // ✅ NO hacer nada, mantener el valor actual
            return;
        }

        // ✅ Si el valor ya está hasheado (empieza con $2y$), no lo vuelvas a hashear
        if (str_starts_with($value, '$2y$')) {
            $this->attributes['password'] = $value;
            return;
        }

        // ✅ Hashear la nueva contraseña
        $this->attributes['password'] = bcrypt($value);
    }

    // ================================================================
    // RELACIONES
    // ================================================================

    /**
     * Get the companies that this user belongs to.
     * Relación N:N con Company (tabla pivote: company_user)
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot('is_default', 'joined_at', 'left_at')
            ->withTimestamps();
    }

    /**
     * Get the user's current active company.
     */
    public function currentCompany()
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    /**
     * Get the user's preferences.
     */
    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    /**
     * Get the user's audit logs.
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Get the transactions created by this user.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // ================================================================
    // SCOPES (Filtros reutilizables)
    // ================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByUsername($query, string $username)
    {
        return $query->where('username', $username);
    }

    public function scopeByEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    // ================================================================
    // MÉTODOS DE NEGOCIO (Tomados del sistema actual, mejorados)
    // ================================================================

    /**
     * Buscar usuario por username
     * Equivalente a findByUsername() del sistema actual
     */
    public function findByUsername(string $username): ?self
    {
        return $this->byUsername($username)->first();
    }

    /**
     * Buscar usuario por email
     * Equivalente a findByEmail() del sistema actual
     */
    public function findByEmail(string $email): ?self
    {
        return $this->byEmail($email)->first();
    }

    /**
     * Actualizar último login
     * Equivalente a updateLastLogin() del sistema actual
     */
    public function recordLogin(string $ip, string $userAgent): bool
    {
        return $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'last_login_user_agent' => $userAgent,
            'failed_login_attempts' => 0,
        ]);
    }

    /**
     * Registrar intento de login fallido
     * Equivalente a logFailedLogin() del sistema actual
     */
    public function recordFailedLogin(): void
    {
        $attempts = $this->failed_login_attempts + 1;

        if ($attempts >= 5) {
            $this->update([
                'failed_login_attempts' => $attempts,
                'locked_until' => now()->addMinutes(15),
            ]);
        } else {
            $this->update([
                'failed_login_attempts' => $attempts,
            ]);
        }
    }

    /**
     * Resetear intentos fallidos
     * Equivalente a resetear manualmente
     */
    public function resetFailedLoginAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Verificar email de usuario
     * Equivalente a verifyEmail() del sistema actual
     */
    public function verifyEmail(): bool
    {
        return $this->update([
            'email_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Contar usuarios por empresa
     * Equivalente a countByCompany() del sistema actual
     */
    public function countByCompany(int $companyId): int
    {
        return $this->whereHas('companies', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })->count();
    }

    /**
     * Obtener usuarios por empresa
     * Equivalente a getByCompany() del sistema actual
     */
    public function getByCompany(int $companyId, int $limit = 100)
    {
        return $this->whereHas('companies', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })->limit($limit)->get();
    }

    /**
     * Verificar si es owner de la empresa
     * Equivalente a isCompanyOwner() del sistema actual
     */
    public function isCompanyOwner(int $companyId): bool
    {
        // Usando Spatie Permission
        return $this->hasRole('owner', 'sanctum') &&
            $this->companies()->where('company_id', $companyId)->exists();
    }

    /**
     * Obtener la empresa por defecto del usuario
     */
    public function getDefaultCompany(): ?Company
    {
        return $this->companies()
            ->wherePivot('is_default', true)
            ->first();
    }

    /**
     * Verificar si el usuario pertenece a una empresa
     */
    public function belongsToCompany(int $companyId): bool
    {
        return $this->companies()->where('companies.id', $companyId)->exists();
    }

    /**
     * Verificar si el usuario tiene un rol específico
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * Verificar si el usuario es super_admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Verificar si el usuario tiene el rol 'user' (y no es super_admin)
     */
    public function isNormalUser(): bool
    {
        return $this->hasRole('user') && !$this->isSuperAdmin();
    }

    /**
     * Verificar si el usuario tiene el rol 'admin'
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->isSuperAdmin();
    }

    /**
     * Verificar si el usuario puede gestionar bancos
     */
    public function canManageBanks(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Verificar si el usuario puede gestionar monedas
     */
    public function canManageCurrencies(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Obtener o crear preferencias del usuario
     */
    public function getPreferences(): UserPreference
    {
        return $this->preferences()->firstOrCreate([]);
    }

    /**
     * Actualizar preferencias del usuario
     */
    public function updatePreferences(array $data): UserPreference
    {
        $preferences = $this->getPreferences();
        $preferences->update($data);
        return $preferences;
    }

    /**
     * Verificar si el usuario está activo
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Verificar si el usuario está bloqueado
     */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Verificar si el usuario está verificado
     */
    public function isVerified(): bool
    {
        return (bool) ($this->email_verified || $this->email_verified_at);
    }

    /**
     * Get the user's display name.
     */
    public function getNameDisplayAttribute(): string
    {
        return $this->name ?? $this->email ?? 'Usuario';
    }

    /**
     * Get the user's name (alias for name).
     * Uso: $user->full_name para compatibilidad con el sistema actual
     */
    public function getFullNameAttribute(): string
    {
        return $this->name ?? $this->email ?? 'Usuario';
    }
}
