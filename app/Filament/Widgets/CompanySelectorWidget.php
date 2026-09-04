<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Services\Context\CompanyContext;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class CompanySelectorWidget extends Widget
{
    protected string $view = 'filament.widgets.company-selector-widget';

    protected int | string | array $columnSpan = 'half';

    public static function canView(): bool
    {
        return Auth::check();
    }

    /**
     * Cambiar a una empresa específica
     */
    public function switchCompany(int $companyId): void
    {
        $user = Auth::user();
        $context = app(CompanyContext::class);

        $companies = $context->getAvailableCompanies();
        $companyIds = array_column($companies, 'id');

        if (!$user->hasRole('super_admin') && !in_array($companyId, $companyIds)) {
            Notification::make()
                ->danger()
                ->title('Acceso denegado')
                ->body('No tienes acceso a esta empresa.')
                ->send();
            return;
        }

        // Cambiar de empresa
        $context->setCurrentCompany($companyId);

        // Actualizar en la base de datos
        $user->current_company_id = $companyId;
        $user->save();

        $companyName = Company::find($companyId)?->name ?? 'Empresa';

        Notification::make()
            ->success()
            ->title('Empresa cambiada')
            ->body("Ahora trabajando en: {$companyName}")
            ->send();

        // ✅ Refrescar la página para actualizar el sidebar
        $this->dispatch('refresh-sidebar');
        $this->redirect(request()->header('Referer', '/admin'));
    }

    public function getData(): array
    {
        $user = Auth::user();
        $context = app(CompanyContext::class);

        $currentCompanyId = $context->getCurrentCompanyId();
        $companyName = $context->getCurrentCompanyName();
        $companies = $context->getAvailableCompanies();

        if (!$companyName && !empty($companies)) {
            $firstCompany = $companies[0] ?? null;
            if ($firstCompany) {
                $companyName = $firstCompany['name'] ?? 'Empresa';
                $currentCompanyId = $firstCompany['id'] ?? null;
            }
        }

        $isSuperAdmin = $user?->hasRole('super_admin') ?? false;

        if ($isSuperAdmin) {
            $allCompanies = Company::where('is_active', true)
                ->orderBy('name')
                ->get()
                ->toArray();
        } else {
            $allCompanies = $companies;
        }

        // Obtener estadísticas de la empresa actual
        $currentCompany = $currentCompanyId ? Company::with(['bankAccounts', 'transactions', 'users'])->find($currentCompanyId) : null;

        $stats = $currentCompany ? [
            'bank_accounts' => $currentCompany->bankAccounts()->where('is_active', true)->count(),
            'transactions' => $currentCompany->transactions()->count(),
            'users' => $currentCompany->users()->count(),
        ] : [
            'bank_accounts' => 0,
            'transactions' => 0,
            'users' => 0,
        ];

        $canViewStats = $user && ($user->hasRole('super_admin') || $user->hasRole('admin') || $user->hasRole('accountant'));

        return [
            'current_company_id' => $currentCompanyId,
            'current_company_name' => $companyName ?? 'Sin empresa',
            'companies' => $allCompanies,
            'is_super_admin' => $isSuperAdmin,
            'user_name' => $user?->name ?? 'Usuario',
            'stats' => $canViewStats ? $stats : null,
            'has_stats' => $canViewStats,
        ];
    }
}
