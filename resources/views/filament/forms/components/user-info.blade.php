@php
    $record = $getRecord();
    if (!$record) return;
    
    $roleCount = $record->roles->count();
    $companyCount = $record->companies->count();
    $transactionCount = $record->transactions->count();
    $lastLogin = $record->last_login_at ? $record->last_login_at->format('d/m/Y H:i') : 'Nunca';
@endphp

<div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4 mt-2">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
        Información del usuario
    </h4>
    
    <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
            <span class="text-gray-500 dark:text-gray-400">ID:</span>
            <span class="ml-1 font-mono">#{{ $record->id }}</span>
        </div>
        
        <div>
            <span class="text-gray-500 dark:text-gray-400">Creado:</span>
            <span class="ml-1">{{ $record->created_at->format('d/m/Y H:i') }}</span>
        </div>
        
        <div>
            <span class="text-gray-500 dark:text-gray-400">Actualizado:</span>
            <span class="ml-1">{{ $record->updated_at->format('d/m/Y H:i') }}</span>
        </div>
        
        <div>
            <span class="text-gray-500 dark:text-gray-400">Último login:</span>
            <span class="ml-1">{{ $lastLogin }}</span>
        </div>
        
        <div>
            <span class="text-gray-500 dark:text-gray-400">Roles asignados:</span>
            <span class="ml-1 font-medium">{{ $roleCount }}</span>
        </div>
        
        <div>
            <span class="text-gray-500 dark:text-gray-400">Empresas:</span>
            <span class="ml-1 font-medium">{{ $companyCount }}</span>
        </div>
        
        <div>
            <span class="text-gray-500 dark:text-gray-400">Transacciones:</span>
            <span class="ml-1 font-medium">{{ $transactionCount }}</span>
        </div>
        
        <div>
            <span class="text-gray-500 dark:text-gray-400">Estado:</span>
            <span class="ml-1 {{ $record->is_active ? 'text-green-600' : 'text-red-600' }}">
                {{ $record->is_active ? 'Activo' : 'Inactivo' }}
            </span>
        </div>
    </div>
    
    @if($record->roles->isNotEmpty())
        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
            <span class="text-xs text-gray-500 dark:text-gray-400">Roles actuales:</span>
            <div class="flex flex-wrap gap-1 mt-1">
                @foreach($record->roles as $role)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                        {{ $role->is_system ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' }}">
                        {{ $role->name }}
                        @if($role->is_system)
                            🔒
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>