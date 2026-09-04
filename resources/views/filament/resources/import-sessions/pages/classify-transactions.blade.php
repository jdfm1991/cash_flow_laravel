<x-filament-panels::page>
    @if($session)
        <div class="mb-4">
            <x-filament::section>
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Sesión #{{ $session->id }}
                        </span>
                        <h2 class="text-xl font-bold">{{ $session->file_name ?? 'Transacciones importadas' }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $session->created_at->format('d/m/Y H:i') }} | 
                            {{ $session->total_rows }} transacciones | 
                            <span class="text-success-500">{{ $session->processed_rows - $session->duplicated_rows - $session->error_rows }} procesadas</span> |
                            <span class="text-warning-500">{{ $session->duplicated_rows }} duplicadas</span> |
                            <span class="text-danger-500">{{ $session->error_rows }} errores</span>
                        </p>
                    </div>
                    <div>
                        <a href="{{ route('filament.admin.resources.import-sessions.index') }}" class="text-primary-500 hover:underline">
                            ← Volver al historial
                        </a>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{ $this->table }}
    @else
        <div class="flex items-center justify-center py-12">
            <div class="text-center">
                <div class="text-4xl mb-4">🔄</div>
                <p class="text-lg text-gray-500">Cargando información de la sesión...</p>
                <p class="text-sm text-gray-400 mt-2">Por favor, espera un momento</p>
            </div>
        </div>
    @endif
</x-filament-panels::page>