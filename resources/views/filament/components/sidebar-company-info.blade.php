@php
    $user = \Illuminate\Support\Facades\Auth::user();
    $context = app(\App\Services\Context\CompanyContext::class);
    $companyName = $context->getCurrentCompanyName();
    $companyId = $context->getCurrentCompanyId();
    $companies = $context->getAvailableCompanies();
    $isSuperAdmin = $user?->hasRole('super_admin') ?? false;
    
    if (!$companyName && !empty($companies)) {
        $firstCompany = $companies[0] ?? null;
        if ($firstCompany) {
            $companyName = $firstCompany['name'] ?? 'Empresa';
            $companyId = $firstCompany['id'] ?? null;
        }
    }
    
    // Obtener el logo de la empresa si existe
    $logoPath = null;
    if ($companyId) {
        $company = \App\Models\Company::find($companyId);
        $logoPath = $company?->logo_path;
    }
@endphp

<div class="filament-sidebar-company-info px-4 py-3 border-b border-gray-200 dark:border-gray-700">
    <div class="flex items-center gap-3">
        <!-- Información de la empresa -->
        <div class="flex-1 min-w-0">
            {{-- <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                {{ $companyName ?? 'Sin empresa' }}
            </div> --}}
        </div>
        
        <!-- Badge de Super Admin -->
        @if($isSuperAdmin)
            <div class="flex-shrink-0">
                <span class="px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 rounded-full">
                    <x-filament::icon
                        icon="heroicon-o-shield-check"
                        class="inline-block w-3 h-3 mr-0.5"
                    />
                    SA
                </span>
            </div>
        @endif
    </div>
</div>