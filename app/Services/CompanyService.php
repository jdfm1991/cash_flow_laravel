<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Models\SubscriptionPlan;
use App\DTOs\CompanyData;
use App\Enums\AuditAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CompanyService
{
    public function __construct(
        protected SubscriptionPlanService $subscriptionPlanService,
    ) {}

    /**
     * Crear una nueva empresa
     */
    public function create(CompanyData $data, ?int $createdBy = null): Company
    {
        // 1. Validar RIF único
        if ($data->taxId && Company::where('tax_id', $data->taxId)->exists()) {
            throw ValidationException::withMessages([
                'tax_id' => ['El RIF ya está registrado en otra empresa'],
            ]);
        }

        // 2. Obtener plan por defecto (Free)
        $defaultPlan = SubscriptionPlan::where('slug', 'free')->first();
        if (!$defaultPlan) {
            $defaultPlan = SubscriptionPlan::first();
        }

        // 3. Crear empresa
        $company = Company::create([
            'name' => $data->name,
            'business_name' => $data->businessName,
            'tax_id' => $data->taxId,
            'email' => $data->email,
            'phone' => $data->phone,
            'address' => $data->address,
            'logo_path' => $data->logo,
            'timezone' => $data->timezone ?? 'America/Caracas',
            'subscription_plan_id' => $data->subscriptionPlanId ?? $defaultPlan?->id,
            'subscription_expires_at' => null,
            'is_active' => true,
            'created_by' => $createdBy,
        ]);

        // 4. Log de auditoría
        Log::info("Empresa creada", [
            'company_id' => $company->id,
            'name' => $company->name,
            'created_by' => $createdBy,
        ]);

        return $company->fresh();
    }

    /**
     * Actualizar una empresa
     */
    public function update(Company $company, CompanyData $data): Company
    {
        // 1. Validar RIF único (excepto la misma empresa)
        if ($data->taxId && Company::where('tax_id', $data->taxId)
            ->where('id', '!=', $company->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'tax_id' => ['El RIF ya está registrado en otra empresa'],
            ]);
        }

        // 2. Actualizar datos
        $company->update([
            'name' => $data->name,
            'business_name' => $data->businessName ?? $company->business_name,
            'tax_id' => $data->taxId ?? $company->tax_id,
            'email' => $data->email ?? $company->email,
            'phone' => $data->phone ?? $company->phone,
            'address' => $data->address ?? $company->address,
            'logo_path' => $data->logo ?? $company->logo_path,
            'timezone' => $data->timezone ?? $company->timezone,
            'is_active' => $data->isActive ?? $company->is_active,
        ]);

        // 3. Actualizar plan de suscripción (si se proporciona)
        if ($data->subscriptionPlanId) {
            $company->subscription_plan_id = $data->subscriptionPlanId;
            $company->save();
        }

        // 4. Invalidar caché
        $this->invalidateCache($company);

        // 5. Log de auditoría
        Log::info("Empresa actualizada", [
            'company_id' => $company->id,
            'name' => $company->name,
        ]);

        return $company->fresh();
    }

    /**
     * Eliminar una empresa (soft delete)
     */
    public function delete(Company $company): bool
    {
        // 1. Validar que no tenga transacciones
        if ($company->transactions()->count() > 0) {
            throw ValidationException::withMessages([
                'company' => ['No se puede eliminar una empresa con transacciones'],
            ]);
        }

        // 2. Validar que no tenga usuarios activos
        if ($company->users()->where('is_active', true)->count() > 0) {
            throw ValidationException::withMessages([
                'company' => ['No se puede eliminar una empresa con usuarios activos'],
            ]);
        }

        // 3. Soft delete
        $result = $company->delete();

        // 4. Invalidar caché
        $this->invalidateCache($company);

        // 5. Log de auditoría
        Log::info("Empresa eliminada", [
            'company_id' => $company->id,
            'name' => $company->name,
        ]);

        return $result;
    }

    /**
     * Restaurar una empresa eliminada
     */
    public function restore(int $companyId): Company
    {
        $company = Company::withTrashed()->findOrFail($companyId);
        $company->restore();

        // Invalidar caché
        $this->invalidateCache($company);

        Log::info("Empresa restaurada", [
            'company_id' => $company->id,
            'name' => $company->name,
        ]);

        return $company;
    }

    /**
     * Obtener empresa por ID
     */
    public function findById(int $id): ?Company
    {
        return Cache::remember("company:{$id}", 3600, function () use ($id) {
            return Company::with(['subscriptionPlan', 'users'])->find($id);
        });
    }

    /**
     * Obtener empresa por RIF
     */
    public function findByTaxId(string $taxId): ?Company
    {
        return Company::where('tax_id', $taxId)->first();
    }

    /**
     * Obtener todas las empresas
     */
    public function getAll(bool $onlyActive = true)
    {
        $query = Company::query();

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return $query->with('subscriptionPlan')
            ->orderBy('name')
            ->get();
    }

    /**
     * Cambiar plan de suscripción
     */
    public function changeSubscriptionPlan(Company $company, int $planId, ?string $expiresAt = null): Company
    {
        // 1. Validar que el plan existe
        $plan = SubscriptionPlan::findOrFail($planId);

        // 2. Verificar límites antes de cambiar
        $this->validateSubscriptionChange($company, $plan);

        // 3. Actualizar plan
        $company->update([
            'subscription_plan_id' => $planId,
            'subscription_expires_at' => $expiresAt ?? $company->subscription_expires_at,
        ]);

        // 4. Invalidar caché
        $this->invalidateCache($company);

        // 5. Log de auditoría
        Log::info("Plan de suscripción cambiado", [
            'company_id' => $company->id,
            'old_plan' => $company->getOriginal('subscription_plan_id'),
            'new_plan' => $planId,
        ]);

        return $company->fresh();
    }

    /**
     * Validar cambio de suscripción
     */
    protected function validateSubscriptionChange(Company $company, SubscriptionPlan $newPlan): void
    {
        // 1. Validar que no exceda el límite de usuarios
        $currentUsers = $company->users()->count();
        if ($currentUsers > $newPlan->max_users) {
            throw ValidationException::withMessages([
                'plan' => [
                    "La empresa tiene {$currentUsers} usuarios. El plan {$newPlan->name} permite máximo {$newPlan->max_users} usuarios."
                ],
            ]);
        }

        // 2. Validar que no exceda el límite de cuentas bancarias
        $currentAccounts = $company->bankAccounts()->count();
        if ($currentAccounts > $newPlan->max_bank_accounts) {
            throw ValidationException::withMessages([
                'plan' => [
                    "La empresa tiene {$currentAccounts} cuentas. El plan {$newPlan->name} permite máximo {$newPlan->max_bank_accounts} cuentas."
                ],
            ]);
        }

        // 3. Validar que no exceda el límite de transacciones mensuales
        $currentTransactions = $company->transactions()
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->count();

        if ($currentTransactions > $newPlan->max_transactions_per_month) {
            throw ValidationException::withMessages([
                'plan' => [
                    "La empresa tiene {$currentTransactions} transacciones este mes. El plan {$newPlan->name} permite máximo {$newPlan->max_transactions_per_month} transacciones mensuales."
                ],
            ]);
        }
    }

    /**
     * Obtener estadísticas de la empresa
     */
    public function getStats(Company $company): array
    {
        return [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'is_active' => $company->is_active,
            ],
            'users' => [
                'total' => $company->users()->count(),
                'active' => $company->users()->where('is_active', true)->count(),
                'max' => $company->subscriptionPlan?->max_users ?? 0,
            ],
            'bank_accounts' => [
                'total' => $company->bankAccounts()->count(),
                'active' => $company->bankAccounts()->where('is_active', true)->count(),
                'max' => $company->subscriptionPlan?->max_bank_accounts ?? 0,
            ],
            'transactions' => [
                'month' => $company->transactions()
                    ->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year)
                    ->count(),
                'max_per_month' => $company->subscriptionPlan?->max_transactions_per_month ?? 0,
            ],
            'subscription' => [
                'plan' => $company->subscriptionPlan?->name ?? 'Sin plan',
                'expires_at' => $company->subscription_expires_at,
                'is_expired' => $company->subscription_expires_at && $company->subscription_expires_at->isPast(),
            ],
        ];
    }

    /**
     * Verificar si la empresa puede agregar más usuarios
     */
    public function canAddUser(Company $company): bool
    {
        $currentUsers = $company->users()->count();
        $maxUsers = $company->subscriptionPlan?->max_users ?? 0;

        return $currentUsers < $maxUsers;
    }

    /**
     * Verificar si la empresa puede agregar más cuentas bancarias
     */
    public function canAddBankAccount(Company $company): bool
    {
        $currentAccounts = $company->bankAccounts()->count();
        $maxAccounts = $company->subscriptionPlan?->max_bank_accounts ?? 0;

        return $currentAccounts < $maxAccounts;
    }

    /**
     * Verificar si la empresa puede agregar más transacciones este mes
     */
    public function canAddTransaction(Company $company): bool
    {
        $currentTransactions = $company->transactions()
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->count();

        $maxTransactions = $company->subscriptionPlan?->max_transactions_per_month ?? 0;

        return $currentTransactions < $maxTransactions;
    }

    /**
     * Invalidar caché de la empresa
     */
    public function invalidateCache(Company $company): void
    {
        Cache::forget("company:{$company->id}");
        Cache::forget("company:{$company->tax_id}");
    }

    /**
     * Invalidar caché de todas las empresas
     */
    public function invalidateAllCache(): void
    {
        Cache::flush();
    }

    /**
     * Obtener empresas con suscripción expirando
     */
    public function getExpiringSoon(int $days = 30)
    {
        return Company::where('subscription_expires_at', '<=', now()->addDays($days))
            ->where('subscription_expires_at', '>', now())
            ->with('subscriptionPlan')
            ->get();
    }

    /**
     * Obtener empresas con suscripción expirada
     */
    public function getExpired()
    {
        return Company::where('subscription_expires_at', '<', now())
            ->where('is_active', true)
            ->with('subscriptionPlan')
            ->get();
    }

    /**
     * Renovar suscripción
     */
    public function renewSubscription(Company $company, int $months = 1): Company
    {
        $currentExpiry = $company->subscription_expires_at;

        if ($currentExpiry && $currentExpiry->isFuture()) {
            $newExpiry = $currentExpiry->addMonths($months);
        } else {
            $newExpiry = now()->addMonths($months);
        }

        $company->update([
            'subscription_expires_at' => $newExpiry,
            'is_active' => true,
        ]);

        $this->invalidateCache($company);

        Log::info("Suscripción renovada", [
            'company_id' => $company->id,
            'expires_at' => $newExpiry,
            'months' => $months,
        ]);

        return $company->fresh();
    }
}