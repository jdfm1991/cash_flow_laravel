<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends SpatiePermission
{

    protected $guard_name = 'web';
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ================================================================
    // RELACIONES
    // ================================================================

    /**
     * Get the roles that have this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_has_permissions',
            'permission_id',
            'role_id'
        );
    }

    /**
     * Get the users that have this permission directly.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'model_has_permissions',
            'permission_id',
            'model_id'
        )->where('model_type', User::class);
    }

    // ================================================================
    // SCOPES
    // ================================================================

    /**
     * Scope a query to only include permissions for a specific guard.
     */
    public function scopeForGuard($query, string $guard)
    {
        return $query->where('guard_name', $guard);
    }
}
