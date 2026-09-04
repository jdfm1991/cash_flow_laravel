<?php

namespace App\Filament\Resources;

use App\Helpers\PermissionHelper;
use App\Services\Context\CompanyContext;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

abstract class BaseResource extends Resource
{
    //use HasPermissions;

    /**
     * ✅ Verificar si el usuario puede ver la lista del recurso
     */
    public static function canViewAny(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Super Admin siempre tiene acceso
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // ✅ Verificar el contexto de empresa
        $context = app(CompanyContext::class);
        if (!$context->isReady()) {
            return false;
        }

        // ✅ Usar PermissionHelper directamente (que SÍ funciona)
        $permission = static::getPermissionBase() . '_view';
        return PermissionHelper::userCan($user, $permission);
    }

    /**
     * ✅ Verificar si el recurso debe registrarse en la navegación
     */
    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Super Admin siempre ve todo
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return static::canViewAny();
    }

    /**
     * ✅ Verificar si el usuario tiene acceso al recurso
     */
    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    /**
     * Obtener el nombre del permiso base
     */
    public static function getPermissionBase(): string
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

    public static function canCreate(): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        if ($user->hasRole('super_admin')) return true;

        $context = app(CompanyContext::class);
        if (!$context->isReady()) return false;

        $permission = static::getPermissionBase() . '_create';
        return PermissionHelper::userCan($user, $permission);
    }

    public static function canEdit($record): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        if ($user->hasRole('super_admin')) return true;

        $context = app(CompanyContext::class);
        if (!$context->isReady()) return false;

        $permission = static::getPermissionBase() . '_edit';
        return PermissionHelper::userCan($user, $permission);
    }

    public static function canDelete($record): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        if ($user->hasRole('super_admin')) return true;

        $context = app(CompanyContext::class);
        if (!$context->isReady()) return false;

        $permission = static::getPermissionBase() . '_delete';
        return PermissionHelper::userCan($user, $permission);
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

    /**
     * Aplicar filtro de empresa a las consultas
     */
    protected static function applyCompanyFilter(Builder $query): Builder
    {
        $context = static::getCompanyContext();

        if (!$context->isReady()) {
            return $query->whereRaw('1 = 0');
        }

        $companyId = $context->getCurrentCompanyId();

        $model = static::getModel();
        if ($model) {
            $table = (new $model)->getTable();
            if ((new $model)->getConnection()->getSchemaBuilder()->hasColumn($table, 'company_id')) {
                return $query->where('company_id', $companyId);
            }
        }

        return $query;
    }
}
