<?php

namespace App\Filament\Resources\ExternalConnections;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\ExternalConnections\Pages\CreateExternalConnection;
use App\Filament\Resources\ExternalConnections\Pages\EditExternalConnection;
use App\Filament\Resources\ExternalConnections\Pages\ListExternalConnections;
use App\Filament\Resources\ExternalConnections\Schemas\ExternalConnectionForm;
use App\Filament\Resources\ExternalConnections\Tables\ExternalConnectionsTable;
use App\Helpers\PermissionHelper;
use App\Models\ExternalConnection;
use App\Services\Context\CompanyContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ExternalConnectionResource extends Resource
{
    protected static ?string $model = ExternalConnection::class;

    // ✅ Navegación
    protected static string|BackedEnum|null $navigationIcon = Heroicon::ServerStack;
    protected static string|UnitEnum|null $navigationGroup = 'Importaciones';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Conexión Externa';
    protected static ?string $pluralModelLabel = 'Conexiones Externas';

    public static function form(Schema $schema): Schema
    {
        return ExternalConnectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExternalConnectionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExternalConnections::route('/'),
            'create' => CreateExternalConnection::route('/create'),
            'edit' => EditExternalConnection::route('/{record}/edit'),
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
        return 'external_connections';
    }
}
