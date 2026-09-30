<?php

namespace App\Filament\Resources\BankAccounts;

use App\Filament\Resources\BankAccounts\Pages\ListBankAccounts;
use App\Filament\Resources\BankAccounts\Schemas\BankAccountForm;
use App\Filament\Resources\BankAccounts\Tables\BankAccountsTable;
use App\Filament\Traits\FiltersByCompany;
use App\Helpers\PermissionHelper;
use App\Models\BankAccount;
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

class BankAccountResource extends Resource
{
    use FiltersByCompany;

    protected static ?string $model = BankAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Banknotes;
    protected static string|UnitEnum|null $navigationGroup = 'Finanzas';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Cuenta Bancaria';
    protected static ?string $pluralModelLabel = 'Cuentas Bancarias';

    public static function form(Schema $schema): Schema
    {
        return BankAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankAccountsTable::configure($table);
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
            'index' => ListBankAccounts::route('/'),
            /* 'create' => CreateBankAccount::route('/create'),
            'edit' => EditBankAccount::route('/{record}/edit'), */
        ];
    }

    /**
     * ✅ Filtrar transacciones por empresa actual
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // ✅ Super Admin puede ver todas las transacciones
        if ($user->hasRole('super_admin')) {
            return $query;
        }

        // ✅ Filtrar por la empresa actual del contexto
        $companyContext = app(CompanyContext::class);
        $companyId = $companyContext->getCurrentCompanyId();

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            // Si no hay empresa en contexto, no mostrar nada
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /**
     * ✅ Filtrar registros individuales por empresa
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->where(function ($query) {
                $user = auth()->user();

                // Super Admin puede ver cualquier registro
                if ($user && $user->hasRole('super_admin')) {
                    return;
                }

                // Usuarios normales solo ven registros de su empresa
                $companyContext = app(CompanyContext::class);
                $companyId = $companyContext->getCurrentCompanyId();

                if ($companyId) {
                    $query->where('company_id', $companyId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            });
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
        return 'bank_accounts';
    }
}
