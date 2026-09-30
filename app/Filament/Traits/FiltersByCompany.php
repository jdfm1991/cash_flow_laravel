<?php

namespace App\Filament\Traits;

use App\Services\Context\CompanyContext;
use Illuminate\Database\Eloquent\Builder;

trait FiltersByCompany
{
    /**
     * ✅ Verificar si el modelo tiene campo company_id
     */
    protected static function modelHasCompanyId(): bool
    {
        $model = static::getModel();
        $table = (new $model)->getTable();
        
        return (new $model)->getConnection()
            ->getSchemaBuilder()
            ->hasColumn($table, 'company_id');
    }

    /**
     * ✅ Aplicar filtro de empresa a la consulta
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // ✅ Super Admin puede ver todo
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // ✅ Si el modelo tiene company_id, filtrar
        if (static::modelHasCompanyId()) {
            $companyContext = app(CompanyContext::class);
            $companyId = $companyContext->getCurrentCompanyId();

            if ($companyId) {
                $query->where('company_id', $companyId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query;
    }
}