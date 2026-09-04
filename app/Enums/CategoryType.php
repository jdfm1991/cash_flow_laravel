<?php

namespace App\Enums;

enum CategoryType: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case ASSET = 'asset';
    case LIABILITY = 'liability';
    case EQUITY = 'equity';

    /**
     * Obtener el label del tipo
     */
    public function label(): string
    {
        return match($this) {
            self::INCOME => 'Ingreso',
            self::EXPENSE => 'Egreso',
            self::ASSET => 'Activo',
            self::LIABILITY => 'Pasivo',
            self::EQUITY => 'Patrimonio',
        };
    }

    /**
     * Obtener el color para el tipo
     */
    public function color(): string
    {
        return match($this) {
            self::INCOME => 'success',
            self::EXPENSE => 'danger',
            self::ASSET => 'primary',
            self::LIABILITY => 'warning',
            self::EQUITY => 'info',
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