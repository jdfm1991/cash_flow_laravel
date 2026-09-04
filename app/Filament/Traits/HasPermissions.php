<?php

namespace App\Filament\Traits;

use App\Helpers\PermissionHelper;
use App\Services\Context\CompanyContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait HasPermissions
{
    /**
     * Verificar si el usuario tiene un permiso específico
     */
    protected static function hasPermission(string $action): bool
    {
        $user = Auth::user();
        if (!$user) {
            Log::info('HasPermissions: No user');
            return false;
        }

        // Super Admin siempre tiene acceso
        if ($user->hasRole('super_admin')) {
            Log::info('HasPermissions: Super admin', ['user' => $user->email]);
            return true;
        }

        $permission = static::getPermissionBase() . '_' . $action;
        $result = PermissionHelper::userCan($user, $permission);

        Log::info('HasPermissions: Check', [
            'user' => $user->email,
            'permission' => $permission,
            'result' => $result
        ]);

        return $result;
    }

    /**
     * Obtener el nombre base del permiso para este recurso
     */
    protected static function getPermissionBase(): string
    {
        $class = class_basename(static::class);
        return strtolower(str_replace('Resource', '', $class));
    }

    /**
     * Obtener el contexto de empresa
     */
    protected static function getCompanyContext(): CompanyContext
    {
        return app(CompanyContext::class);
    }

    /**
     * Verificar si el usuario puede ver la lista del recurso
     */
    public static function canViewAny(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        $context = static::getCompanyContext();
        if (!$context->isReady()) {
            return false;
        }

        return static::hasPermission('view');
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        if ($user->hasRole('super_admin')) return true;

        $context = static::getCompanyContext();
        if (!$context->isReady()) return false;

        return static::hasPermission('create');
    }

    public static function canEdit($record): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        if ($user->hasRole('super_admin')) return true;

        $context = static::getCompanyContext();
        if (!$context->isReady()) return false;

        return static::hasPermission('edit');
    }

    public static function canDelete($record): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        if ($user->hasRole('super_admin')) return true;

        $context = static::getCompanyContext();
        if (!$context->isReady()) return false;

        return static::hasPermission('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::canDelete(null);
    }

    public static function canCreateBulk(): bool
    {
        return static::canCreate();
    }

    public static function canEditBulk(): bool
    {
        return static::canEdit(null);
    }

    public static function canImport(): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        if ($user->hasRole('super_admin')) return true;

        $context = static::getCompanyContext();
        if (!$context->isReady()) return false;

        return static::hasPermission('import');
    }

    public static function canExport(): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        if ($user->hasRole('super_admin')) return true;

        $context = static::getCompanyContext();
        if (!$context->isReady()) return false;

        return static::hasPermission('export');
    }
}
