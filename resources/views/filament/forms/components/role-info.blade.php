@php
    $record = $getRecord();
    if (!$record) return;
    
    $permissionCount = $record->permissions->count();
    $userCount = $record->users()->count();
    $isSystem = $record->is_system;
    
    // Obtener permisos agrupados
    $groupedPermissions = \App\Services\PermissionService::getGroupedPermissionsForUI();
    $permissionsByGroup = [];
    
    foreach ($record->permissions as $permission) {
        $parts = explode('_', $permission->name);
        $action = array_pop($parts);
        $group = implode('_', $parts);
        
        $groupLabel = $groupedPermissions[$group]['label'] ?? $group;
        
        if (!isset($permissionsByGroup[$groupLabel])) {
            $permissionsByGroup[$groupLabel] = [];
        }
        
        $permissionsByGroup[$groupLabel][] = [
            'name' => $permission->name,
            'action' => $action,
            'action_label' => \App\Services\PermissionService::getActionLabel($action),
            'action_color' => \App\Services\PermissionService::getActionColor($action),
        ];
    }
@endphp

<div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4 mt-2">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
        Información del rol
    </h4>
    
    <div class="grid grid-cols-3 gap-3 text-sm">
        <div>
            <span class="text-gray-500 dark:text-gray-400">ID:</span>
            <span class="ml-1 font-mono">#{{ $record->id }}</span>
        </div>
        
        <div>
            <span class="text-gray-500 dark:text-gray-400">Permisos:</span>
            <span class="ml-1 font-medium">{{ $permissionCount }}</span>
        </div>
        
        <div>
            <span class="text-gray-500 dark:text-gray-400">Usuarios:</span>
            <span class="ml-1 font-medium">{{ $userCount }}</span>
        </div>
        
        <div>
            <span class="text-gray-500 dark:text-gray-400">Tipo:</span>
            <span class="ml-1">
                @if($isSystem)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                        🔒 Sistema
                    </span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                        Personalizado
                    </span>
                @endif
            </span>
        </div>
        
        <div>
            <span class="text-gray-500 dark:text-gray-400">Creado:</span>
            <span class="ml-1">{{ $record->created_at->format('d/m/Y H:i') }}</span>
        </div>
        
        <div>
            <span class="text-gray-500 dark:text-gray-400">Actualizado:</span>
            <span class="ml-1">{{ $record->updated_at->format('d/m/Y H:i') }}</span>
        </div>
    </div>
    
    @if($record->description)
        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <span class="text-xs text-gray-500 dark:text-gray-400">Descripción:</span>
            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $record->description }}</p>
        </div>
    @endif
    
    @if($permissionCount > 0)
        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <span class="text-xs text-gray-500 dark:text-gray-400">Permisos asignados por módulo:</span>
            <div class="mt-2 space-y-2">
                @foreach($permissionsByGroup as $group => $permissions)
                    <div>
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ $group }}</span>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach($permissions as $permission)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                                    bg-{{ $permission['action_color'] }}-100 text-{{ $permission['action_color'] }}-800 
                                    dark:bg-{{ $permission['action_color'] }}-900/30 dark:text-{{ $permission['action_color'] }}-400">
                                    {{ $permission['action_label'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <span class="text-xs text-gray-400 dark:text-gray-500">Este rol no tiene permisos asignados.</span>
        </div>
    @endif
</div>