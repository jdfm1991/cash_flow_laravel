<?php

namespace App\Filament\Resources\Companies;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Companies\Schemas\CompanyForm;
use App\Filament\Resources\Companies\Tables\CompaniesTable;
use App\Helpers\PermissionHelper;
use App\Models\Company;
use App\Services\Context\CompanyContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingOffice;
    protected static string|UnitEnum|null $navigationGroup = 'Administración';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Empresa';
    protected static ?string $pluralModelLabel = 'Empresas';

    public static function form(Schema $schema): Schema
    {
        return CompanyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompaniesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            /* 'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'), */
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * ✅ SOBRESCRIBIR canViewAny DIRECTAMENTE
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

        $context = app(CompanyContext::class);
        if (!$context->isReady()) {
            return false;
        }

        $permission = static::getPermissionBase() . '_view';
        return PermissionHelper::userCan($user, $permission);
    }

    /**
     * ✅ SOBRESCRIBIR shouldRegisterNavigation
     */
    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return static::canViewAny();
    }

    /**
     * ✅ SOBRESCRIBIR canAccess
     */
    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    /**
     * ✅ Sobrescribir otros métodos de permisos
     */
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
     * ✅ Definir el nombre base del permiso
     */
    public static function getPermissionBase(): string
    {
        // Cambiar según el recurso
        return 'companies';
    }
}
