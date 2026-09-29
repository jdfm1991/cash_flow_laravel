@php
    $user = \Illuminate\Support\Facades\Auth::user();
    $context = app(\App\Services\Context\CompanyContext::class);
    $currentCompanyId = $context->getCurrentCompanyId();
    $companyName = $context->getCurrentCompanyName();
    $companies = $context->getAvailableCompanies();
    $isSuperAdmin = $user?->hasRole('super_admin') ?? false;
    
    if (!$companyName && !empty($companies)) {
        $firstCompany = $companies[0] ?? null;
        if ($firstCompany) {
            $companyName = $firstCompany['name'] ?? 'Empresa';
            $currentCompanyId = $firstCompany['id'] ?? null;
        }
    }
    
    if ($isSuperAdmin) {
        $allCompanies = \App\Models\Company::where('is_active', true)->orderBy('name')->get();
    } else {
        $allCompanies = collect($companies);
    }
    
    $currentCompany = $currentCompanyId ? \App\Models\Company::with(['bankAccounts', 'transactions', 'users'])->find($currentCompanyId) : null;
    $stats = $currentCompany ? [
        'bank_accounts' => $currentCompany->bankAccounts()->where('is_active', true)->count(),
        'transactions' => $currentCompany->transactions()->count(),
        'users' => $currentCompany->users()->count(),
    ] : ['bank_accounts' => 0, 'transactions' => 0, 'users' => 0];
    
    $canViewStats = $user && ($user->hasRole('super_admin') || $user->hasRole('admin') || $user->hasRole('accountant'));
@endphp

<x-filament::widget>
    <x-filament::section>
        <div class="space-y-4">
            <!-- Título -->
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    <x-filament::icon
                        icon="heroicon-o-building-office"
                        class="inline-block w-5 h-5 mr-2 text-primary-500"
                    />
                    Contexto de Empresa
                </h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    👤 {{ $user?->name ?? 'Usuario' }}
                </span>
            </div>

            <!-- Empresa Actual -->
            <div class="flex items-center gap-4 p-4 bg-primary-50 dark:bg-primary-900/20 rounded-lg border border-primary-200 dark:border-primary-800">
                <div class="flex-shrink-0 w-12 h-12 bg-primary-100 dark:bg-primary-800 rounded-full flex items-center justify-center">
                    <x-filament::icon
                        icon="heroicon-o-building-office"
                        class="w-6 h-6 text-primary-600 dark:text-primary-400"
                    />
                </div>
                
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Empresa Activa
                    </div>
                    <div class="text-lg font-bold text-gray-900 dark:text-gray-100 truncate">
                        {{ $companyName ?? 'Sin empresa seleccionada' }}
                    </div>
                    @if($currentCompanyId)
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            ID: #{{ $currentCompanyId }}
                        </div>
                    @endif
                </div>

                @if(count($allCompanies) > 1)
                    <div x-data="{ showCompanies: false }" class="relative">
                        <x-filament::button
                            @click="showCompanies = !showCompanies"
                            @click.away="showCompanies = false"
                            color="gray"
                            size="sm"
                            tag="button"
                        >
                            <x-filament::icon
                                icon="heroicon-o-arrow-path"
                                class="w-4 h-4 mr-1"
                            />
                            Cambiar
                            <x-filament::icon
                                icon="heroicon-o-chevron-down"
                                class="w-4 h-4 ml-1"
                                x-bind:class="showCompanies ? 'rotate-180' : ''"
                            />
                        </x-filament::button>
                        
                        <div 
                            x-show="showCompanies"
                            x-transition
                            class="absolute right-0 mt-2 w-80 max-h-96 overflow-y-auto bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50"
                        >
                            <div class="p-2">
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 px-3 py-2">
                                    {{ $isSuperAdmin ? 'Todas las empresas' : 'Tus empresas' }}
                                </div>
                                
                                @foreach($allCompanies as $company)
                                    <button 
                                        wire:click="switchCompany({{ $company['id'] }})"
                                        @click="showCompanies = false"
                                        class="flex items-center w-full px-3 py-2 text-sm rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition {{ $currentCompanyId == $company['id'] ? 'bg-gray-100 dark:bg-gray-700' : '' }}"
                                    >
                                        <span class="flex-1 text-left">
                                            {{ $company['name'] }}
                                        </span>
                                        @if($currentCompanyId == $company['id'])
                                            <span class="px-2 py-0.5 text-xs font-medium bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300 rounded-full">
                                                Activa
                                            </span>
                                        @endif
                                    </button>
                                @endforeach
                                
                                @if(count($allCompanies) == 0)
                                    <div class="text-sm text-gray-500 dark:text-gray-400 px-3 py-4 text-center">
                                        No hay empresas disponibles
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Estadísticas -->
            @if($canViewStats && $currentCompanyId)
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">
                        Resumen de la empresa
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                            <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                                <x-filament::icon
                                    icon="heroicon-o-banknotes"
                                    class="w-4 h-4 text-blue-600 dark:text-blue-400"
                                />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ number_format($stats['transactions']) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Transacciones</div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                            <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                                <x-filament::icon
                                    icon="heroicon-o-building-library"
                                    class="w-4 h-4 text-green-600 dark:text-green-400"
                                />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ number_format($stats['bank_accounts']) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Cuentas bancarias</div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                            <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                                <x-filament::icon
                                    icon="heroicon-o-users"
                                    class="w-4 h-4 text-purple-600 dark:text-purple-400"
                                />
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ number_format($stats['users']) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Usuarios</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Pie de página -->
            @if($isSuperAdmin)
                <div class="text-xs text-gray-400 dark:text-gray-500 border-t border-gray-200 dark:border-gray-700 pt-3">
                    <x-filament::icon
                        icon="heroicon-o-shield-check"
                        class="inline-block w-3 h-3 mr-1"
                    />
                    Modo Super Administrador - Puedes acceder a todas las empresas
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament::widget>