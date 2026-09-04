<?php

namespace App\Enums;

enum AccountType: string
{
    case CURRENT = 'corriente';
    case SAVINGS = 'ahorros';
    case PAYROLL = 'nomina';
    case INVESTMENT = 'inversion';
    case PETTY_CASH = 'caja_chica';
    case CASH = 'efectivo';
    case CREDIT_CARD = 'tarjeta_credito';
    case VIRTUAL = 'virtual';

    /**
     * Obtener el label del tipo
     */
    public function label(): string
    {
        return match($this) {
            self::CURRENT => 'Cuenta Corriente',
            self::SAVINGS => 'Cuenta de Ahorros',
            self::PAYROLL => 'Cuenta de Nómina',
            self::INVESTMENT => 'Cuenta de Inversión',
            self::PETTY_CASH => 'Caja Chica',
            self::CASH => 'Efectivo',
            self::CREDIT_CARD => 'Tarjeta de Crédito',
            self::VIRTUAL => 'Cuenta Virtual',
        };
    }

    /**
     * Obtener el icono para el tipo
     */
    public function icon(): string
    {
        return match($this) {
            self::CURRENT => 'bi-bank',
            self::SAVINGS => 'bi-piggy-bank',
            self::PAYROLL => 'bi-wallet2',
            self::INVESTMENT => 'bi-graph-up-arrow',
            self::PETTY_CASH => 'bi-cash',
            self::CASH => 'bi-cash-stack',
            self::CREDIT_CARD => 'bi-credit-card',
            self::VIRTUAL => 'bi-wallet',
        };
    }

    /**
     * Obtener el color para el tipo
     */
    public function color(): string
    {
        return match($this) {
            self::CURRENT => 'primary',
            self::SAVINGS => 'success',
            self::PAYROLL => 'info',
            self::INVESTMENT => 'warning',
            self::PETTY_CASH => 'secondary',
            self::CASH => 'success',
            self::CREDIT_CARD => 'danger',
            self::VIRTUAL => 'gray',
        };
    }

    /**
     * Verificar si es una cuenta bancaria
     */
    public function isBankAccount(): bool
    {
        return in_array($this, [
            self::CURRENT,
            self::SAVINGS,
            self::PAYROLL,
            self::INVESTMENT,
        ]);
    }

    /**
     * Verificar si es una cuenta de efectivo
     */
    public function isCashAccount(): bool
    {
        return in_array($this, [
            self::PETTY_CASH,
            self::CASH,
        ]);
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
    public static function labels(): array
    {
        return array_column(self::cases(), 'label');
    }

    /**
     * Obtener todos los valores como array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}