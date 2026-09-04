<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Schemas\AuditLogForm;
use App\Filament\Resources\AuditLogs\Tables\AuditLogsTable;
use App\Helpers\PermissionHelper;
use App\Models\AuditLog;
use App\Services\Context\CompanyContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;
    protected static string|UnitEnum|null $navigationGroup = 'Seguridad';
    protected static ?int $navigationSort = 1;
    protected static ?string $modelLabel = 'Auditoría';
    protected static ?string $pluralModelLabel = 'Auditorías';

    public static function form(Schema $schema): Schema
    {
        return AuditLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
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
            'index' => ListAuditLogs::route('/'),
            /* 'create' => CreateAuditLog::route('/create'),
            'edit' => EditAuditLog::route('/{record}/edit'), */
        ];
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
    return 'audit_logs';
}
}
