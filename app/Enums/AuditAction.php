<?php

namespace App\Enums;

enum AuditAction: string
{
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case RESTORE = 'restore';
    case VIEW = 'view';
    case EXPORT = 'export';
    case IMPORT = 'import';
    case SWITCH_COMPANY = 'switch_company';
    case LOGIN_FAILED = 'login_failed';
    case PASSWORD_RESET = 'password_reset';

    /**
     * Obtener el label de la acción
     */
    public function label(): string
    {
        return match($this) {
            self::LOGIN => 'Inicio de sesión',
            self::LOGOUT => 'Cierre de sesión',
            self::CREATE => 'Creación',
            self::UPDATE => 'Actualización',
            self::DELETE => 'Eliminación',
            self::RESTORE => 'Restauración',
            self::VIEW => 'Visualización',
            self::EXPORT => 'Exportación',
            self::IMPORT => 'Importación',
            self::SWITCH_COMPANY => 'Cambio de empresa',
            self::LOGIN_FAILED => 'Intento de login fallido',
            self::PASSWORD_RESET => 'Recuperación de contraseña',
        };
    }

    /**
     * Verificar si es una acción de usuario
     */
    public function isUserAction(): bool
    {
        return in_array($this, [
            self::LOGIN,
            self::LOGOUT,
            self::LOGIN_FAILED,
            self::PASSWORD_RESET,
        ]);
    }

    /**
     * Verificar si es una acción CRUD
     */
    public function isCrudAction(): bool
    {
        return in_array($this, [
            self::CREATE,
            self::UPDATE,
            self::DELETE,
            self::RESTORE,
        ]);
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}