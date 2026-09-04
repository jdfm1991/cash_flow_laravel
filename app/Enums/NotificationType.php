<?php

namespace App\Enums;

enum NotificationType: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';

    /**
     * Obtener el label del tipo
     */
    public function label(): string
    {
        return match($this) {
            self::INFO => 'Información',
            self::SUCCESS => 'Éxito',
            self::WARNING => 'Advertencia',
            self::ERROR => 'Error',
        };
    }

    /**
     * Obtener el color para el tipo
     */
    public function color(): string
    {
        return match($this) {
            self::INFO => 'info',
            self::SUCCESS => 'success',
            self::WARNING => 'warning',
            self::ERROR => 'danger',
        };
    }

    /**
     * Obtener todos los valores como array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}