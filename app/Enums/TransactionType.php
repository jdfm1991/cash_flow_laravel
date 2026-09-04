<?php

namespace App\Enums;

enum TransactionType: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case TRANSFER = 'transfer';

    /**
     * Obtener el label del tipo
     */
    public function label(): string
    {
        return match($this) {
            self::INCOME => 'Ingreso',
            self::EXPENSE => 'Egreso',
            self::TRANSFER => 'Transferencia',
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
            self::TRANSFER => 'warning',
        };
    }

    /**
     * Obtener el signo (+1, -1, 0)
     */
    public function sign(): int
    {
        return match($this) {
            self::INCOME => 1,
            self::EXPENSE => -1,
            self::TRANSFER => 0,
        };
    }

    /**
     * Obtener todos los tipos como array para select
     */
    public static function options(): array
    {
        return [
            self::INCOME->value => self::INCOME->label(),
            self::EXPENSE->value => self::EXPENSE->label(),
            self::TRANSFER->value => self::TRANSFER->label(),
        ];
    }

    /**
     * Obtener todos los tipos como array de valores
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}