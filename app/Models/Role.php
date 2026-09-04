<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Permission;

class Role extends SpatieRole
{

    protected $guard_name = 'web';
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'is_system',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_system' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ================================================================
    // RELACIONES
    // ================================================================

    /**
     * Get the users that have this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'model_has_roles',
            'role_id',
            'model_id'
        )->where('model_type', User::class);
    }

    /**
     * Get the permissions that belong to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_has_permissions',
            'role_id',
            'permission_id'
        );
    }

    // ================================================================
    // SCOPES
    // ================================================================

    /**
     * Scope a query to only include system roles.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope a query to only include non-system roles.
     */
    public function scopeNotSystem($query)
    {
        return $query->where('is_system', false);
    }

    // ================================================================
    // MÉTODOS DE AYUDA
    // ================================================================

    /**
     * Check if this is a system role.
     */
    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }

    /**
     * Get the guard name (defaults to 'web').
     */
    public function getGuardName(): string
    {
        return $this->guard_name ?? 'web';
    }

    /**
     * ✅ Verificar si el rol tiene un permiso específico
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('name', $permission)->exists();
    }

    /**
     * ✅ Obtener todos los permisos del rol como array de nombres
     */
    public function getPermissionNamesAttribute(): array
    {
        return $this->permissions->pluck('name')->toArray();
    }

    /**
     * Obtener el display name del rol
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->name;
        if ($this->is_system) {
            $name .= ' 🔒';
        }
        return $name;
    }
}
