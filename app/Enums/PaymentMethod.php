<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case BANK = 'bank';
    case TRANSFER = 'transfer';

    /**
     * Obtener el label del método de pago
     */
    public function label(): string
    {
        return match($this) {
            self::CASH => 'Efectivo',
            self::BANK => 'Transferencia Bancaria',
            self::TRANSFER => 'Transferencia entre cuentas',
        };
    }

    /**
     * Obtener el color para el método de pago
     */
    public function color(): string
    {
        return match($this) {
            self::CASH => 'success',
            self::BANK => 'primary',
            self::TRANSFER => 'warning',
        };
    }

    /**
     * Obtener todas las opciones para select
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }

    /**
     * Obtener todos los valores como array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}