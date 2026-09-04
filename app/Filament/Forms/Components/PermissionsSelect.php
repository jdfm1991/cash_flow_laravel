<?php

namespace App\Filament\Forms\Components;

use App\Services\PermissionService;
use Closure;
use Filament\Forms\Components\Select;

class PermissionsSelect extends Select
{
    protected bool|Closure $showGroupLabels = true;

    public function hideGroupLabels(bool|Closure $condition = true): static
    {
        $this->showGroupLabels = !$condition;
        return $this;
    }

    /**
     * ✅ Obtener opciones para validación y visualización
     */
    public function getOptions(): array
    {
        // ✅ Obtener permisos directamente desde el servicio
        $permissions = PermissionService::getGroupedPermissionsForUI();
        $options = [];

        foreach ($permissions as $group => $data) {
            $groupLabel = $this->evaluate($this->showGroupLabels) ? $data['label'] : '';
            
            foreach ($data['permissions'] as $permission) {
                if ($groupLabel) {
                    $options[$groupLabel][$permission['name']] = $permission['label'];
                } else {
                    $options[$permission['name']] = $permission['label'];
                }
            }
        }

        return $options;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchable()
            ->preload()
            ->multiple()
            ->helperText('Selecciona los permisos que tendrá este rol.');
    }

    /**
     * ✅ Método make con firma correcta
     */
    public static function make(?string $name = null): static
    {
        $static = parent::make($name);
        return $static;
    }
}