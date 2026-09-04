<?php

namespace App\Filament\Resources\Currencies;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Currencies\Pages\ListCurrencies;
use App\Filament\Resources\Currencies\Schemas\CurrencyForm;
use App\Filament\Resources\Currencies\Tables\CurrenciesTable;
use App\Helpers\PermissionHelper;
use App\Models\Currency;
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

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    // ✅ Agregar navegación completa
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Catálogos';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Moneda';
    protected static ?string $pluralModelLabel = 'Monedas';

    public static function form(Schema $schema): Schema
    {
        return CurrencyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CurrenciesTable::configure($table);
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
            'index' => ListCurrencies::route('/'),
            //'create' => CreateCurrency::route('/create'),
            //'edit' => EditCurrency::route('/{record}/edit'),
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
    return 'currencies';
}
}
