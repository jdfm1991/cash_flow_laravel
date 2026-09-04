<?php

namespace App\Enums;

enum ImportStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    /**
     * Obtener el label del estado
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pendiente',
            self::PROCESSING => 'Procesando',
            self::COMPLETED => 'Completado',
            self::FAILED => 'Fallido',
        };
    }

    /**
     * Obtener el color para el estado
     */
    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::PROCESSING => 'info',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
        };
    }

    /**
     * Verificar si es un estado final
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::FAILED,
        ]);
    }

    /**
     * Verificar si se puede iniciar
     */
    public function canStart(): bool
    {
        return $this === self::PENDING;
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