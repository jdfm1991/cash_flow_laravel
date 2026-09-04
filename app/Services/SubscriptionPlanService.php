<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SubscriptionPlanService
{
    /**
     * Crear un nuevo plan de suscripción
     */
    public function create(array $data): SubscriptionPlan
    {
        if (SubscriptionPlan::where('slug', $data['slug'])->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['El slug ya está en uso'],
            ]);
        }

        if (SubscriptionPlan::where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages([
                'name' => ['El nombre ya está en uso'],
            ]);
        }

        $plan = SubscriptionPlan::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'max_users' => $data['max_users'] ?? 5,
            'max_bank_accounts' => $data['max_bank_accounts'] ?? 50,
            'max_transactions_per_month' => $data['max_transactions_per_month'] ?? 500,
            'features' => $data['features'] ?? [],
            'price' => $data['price'] ?? 0,
            'currency_id' => $data['currency_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        $this->invalidateCache();

        Log::info("Plan de suscripción creado", [
            'plan_id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
        ]);

        return $plan;
    }

    /**
     * Actualizar un plan de suscripción
     */
    public function update(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        if (isset($data['slug']) && SubscriptionPlan::where('slug', $data['slug'])
            ->where('id', '!=', $plan->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'slug' => ['El slug ya está en uso'],
            ]);
        }

        if (isset($data['name']) && SubscriptionPlan::where('name', $data['name'])
            ->where('id', '!=', $plan->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'name' => ['El nombre ya está en uso'],
            ]);
        }

        if ($this->isDowngrading($plan, $data)) {
            $this->validateDowngrade($plan, $data);
        }

        $plan->update([
            'name' => $data['name'] ?? $plan->name,
            'slug' => $data['slug'] ?? $plan->slug,
            'description' => $data['description'] ?? $plan->description,
            'max_users' => $data['max_users'] ?? $plan->max_users,
            'max_bank_accounts' => $data['max_bank_accounts'] ?? $plan->max_bank_accounts,
            'max_transactions_per_month' => $data['max_transactions_per_month'] ?? $plan->max_transactions_per_month,
            'features' => $data['features'] ?? $plan->features,
            'price' => $data['price'] ?? $plan->price,
            'currency_id' => $data['currency_id'] ?? $plan->currency_id,
            'is_active' => $data['is_active'] ?? $plan->is_active,
            'sort_order' => $data['sort_order'] ?? $plan->sort_order,
        ]);

        $this->invalidateCache();

        Log::info("Plan de suscripción actualizado", [
            'plan_id' => $plan->id,
            'name' => $plan->name,
        ]);

        return $plan->fresh();
    }

    /**
     * Eliminar un plan de suscripción
     */
    public function delete(SubscriptionPlan $plan): bool
    {
        if ($plan->companies()->count() > 0) {
            throw ValidationException::withMessages([
                'plan' => ['No se puede eliminar un plan que tiene empresas asociadas'],
            ]);
        }

        if ($this->isDefaultPlan($plan)) {
            throw ValidationException::withMessages([
                'plan' => ['No se puede eliminar el plan por defecto'],
            ]);
        }

        $result = $plan->delete();
        $this->invalidateCache();

        Log::info("Plan de suscripción eliminado", [
            'plan_id' => $plan->id,
            'name' => $plan->name,
        ]);

        return $result;
    }

    /**
     * Obtener plan por ID
     */
    public function findById(int $id): ?SubscriptionPlan
    {
        return Cache::remember("subscription_plan:{$id}", 3600, function () use ($id) {
            return SubscriptionPlan::with('currency')->find($id);
        });
    }

    /**
     * Obtener plan por slug
     */
    public function findBySlug(string $slug): ?SubscriptionPlan
    {
        return Cache::remember("subscription_plan:slug:{$slug}", 3600, function () use ($slug) {
            return SubscriptionPlan::with('currency')->where('slug', $slug)->first();
        });
    }

    /**
     * Obtener todos los planes activos
     */
    public function getActivePlans()
    {
        return Cache::remember('subscription_plans:active', 3600, function () {
            return SubscriptionPlan::active()
                ->ordered()
                ->with('currency')
                ->get();
        });
    }

    /**
     * Obtener todos los planes
     */
    public function getAllPlans()
    {
        return Cache::remember('subscription_plans:all', 3600, function () {
            return SubscriptionPlan::ordered()
                ->with('currency')
                ->get();
        });
    }

    /**
     * Obtener plan por defecto (Free)
     */
    public function getDefaultPlan(): ?SubscriptionPlan
    {
        return Cache::remember('subscription_plan:default', 3600, function () {
            return SubscriptionPlan::where('slug', 'free')
                ->orWhere('price', 0)
                ->first();
        });
    }

    /**
     * Verificar si un plan tiene una característica específica
     */
    public function hasFeature(SubscriptionPlan $plan, string $feature): bool
    {
        return $plan->hasFeature($feature);
    }

    /**
     * Obtener opciones para select
     * ✅ CORREGIDO: Verifica que cada elemento sea un objeto
     */
    public function getOptions(): array
    {
        $options = [];
        $plans = $this->getActivePlans();

        // Verificar que $plans es una colección iterable
        if ($plans && $plans->isNotEmpty()) {
            foreach ($plans as $plan) {
                // Asegurar que $plan es un objeto SubscriptionPlan
                if ($plan instanceof SubscriptionPlan) {
                    $options[$plan->id] = $plan->name . ' (' . $plan->getFormattedPriceAttribute() . ')';
                }
            }
        }

        return $options;
    }

    /**
     * Obtener plan free (gratuito)
     */
    public function getFreePlan(): ?SubscriptionPlan
    {
        return $this->findBySlug('free');
    }

    /**
     * Verificar si un plan es gratuito
     */
    public function isFree(SubscriptionPlan $plan): bool
    {
        return $plan->isFree();
    }

    /**
     * Invalidar caché de planes
     */
    public function invalidateCache(): void
    {
        Cache::forget('subscription_plans:active');
        Cache::forget('subscription_plans:all');
        Cache::forget('subscription_plan:default');
    }

    /**
     * Obtener planes con estadísticas de uso
     */
    public function getPlansWithStats(): array
    {
        $plans = $this->getAllPlans();
        $result = [];

        foreach ($plans as $plan) {
            if (!$plan instanceof SubscriptionPlan) {
                continue;
            }

            $companies = Company::where('subscription_plan_id', $plan->id)->get();

            $result[] = [
                'plan' => $plan,
                'companies_count' => $companies->count(),
                'users_total' => $companies->sum(function ($company) {
                    return $company->users()->count();
                }),
                'transactions_total' => $companies->sum(function ($company) {
                    return $company->transactions()->count();
                }),
            ];
        }

        return $result;
    }

    /**
     * Verificar si se está reduciendo límites
     */
    protected function isDowngrading(SubscriptionPlan $plan, array $data): bool
    {
        $fields = ['max_users', 'max_bank_accounts', 'max_transactions_per_month'];

        foreach ($fields as $field) {
            if (isset($data[$field]) && $data[$field] < $plan->$field) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validar downgrade (reducción de límites)
     */
    protected function validateDowngrade(SubscriptionPlan $plan, array $data): void
    {
        $companies = Company::where('subscription_plan_id', $plan->id)->get();

        foreach ($companies as $company) {
            if (isset($data['max_users']) && $company->users()->count() > $data['max_users']) {
                throw ValidationException::withMessages([
                    'max_users' => [
                        "La empresa '{$company->name}' tiene {$company->users()->count()} usuarios. "
                        . "El nuevo límite sería {$data['max_users']}."
                    ],
                ]);
            }

            if (isset($data['max_bank_accounts']) && $company->bankAccounts()->count() > $data['max_bank_accounts']) {
                throw ValidationException::withMessages([
                    'max_bank_accounts' => [
                        "La empresa '{$company->name}' tiene {$company->bankAccounts()->count()} cuentas bancarias. "
                        . "El nuevo límite sería {$data['max_bank_accounts']}."
                    ],
                ]);
            }

            if (isset($data['max_transactions_per_month'])) {
                $currentMonthTransactions = $company->transactions()
                    ->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year)
                    ->count();

                if ($currentMonthTransactions > $data['max_transactions_per_month']) {
                    throw ValidationException::withMessages([
                        'max_transactions_per_month' => [
                            "La empresa '{$company->name}' tiene {$currentMonthTransactions} transacciones este mes. "
                            . "El nuevo límite sería {$data['max_transactions_per_month']}."
                        ],
                    ]);
                }
            }
        }
    }

    /**
     * Verificar si es el plan por defecto
     */
    protected function isDefaultPlan(SubscriptionPlan $plan): bool
    {
        $default = $this->getDefaultPlan();
        return $default && $default->id === $plan->id;
    }
}