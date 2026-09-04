<?php

namespace App\Enums;

enum ImportSource: string
{
    case EXCEL = 'excel';
    case MIGRATION = 'migration';
    case CSV = 'csv';
    case PDF = 'pdf';
    case MANUAL = 'manual';
    case API = 'api';

    /**
     * Obtener el label de la fuente
     */
    public function label(): string
    {
        return match($this) {
            self::EXCEL => 'Excel',
            self::MIGRATION => 'Migración',
            self::CSV => 'CSV',
            self::PDF => 'PDF',
            self::MANUAL => 'Manual',
            self::API => 'API',
        };
    }

    /**
     * Obtener el color para la fuente
     */
    public function color(): string
    {
        return match($this) {
            self::EXCEL => 'success',
            self::MIGRATION => 'warning',
            self::CSV => 'info',
            self::PDF => 'danger',
            self::MANUAL => 'primary',
            self::API => 'secondary',
        };
    }

    /**
     * Verificar si es un archivo
     */
    public function isFile(): bool
    {
        return in_array($this, [
            self::EXCEL,
            self::CSV,
            self::PDF,
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