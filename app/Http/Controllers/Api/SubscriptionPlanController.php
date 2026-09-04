<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionPlan\StoreSubscriptionPlanRequest;
use App\Http\Requests\SubscriptionPlan\UpdateSubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionPlanService;
use App\Traits\HasCompanyContext;
use App\Traits\LogsActivity;
use App\Traits\HandlesApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    use HasCompanyContext, LogsActivity, HandlesApiResponses;

    public function __construct(
        protected SubscriptionPlanService $subscriptionPlanService,
    ) {}

    /**
     * GET /api/subscription-plans
     * Listar todos los planes de suscripción
     */
    public function index(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('all') && 
            auth()->user()->hasRole('super_admin');

        $plans = $includeInactive 
            ? SubscriptionPlan::with('currency')->ordered()->get()
            : SubscriptionPlan::with('currency')->active()->ordered()->get();

        return $this->successResponse($plans);
    }

    /**
     * GET /api/subscription-plans/{id}
     * Obtener un plan de suscripción específico
     */
    public function show(int $id): JsonResponse
    {
        $plan = SubscriptionPlan::with('currency')->find($id);

        if (!$plan) {
            return $this->notFoundResponse('Plan de suscripción no encontrado');
        }

        return $this->successResponse($plan);
    }

    /**
     * GET /api/subscription-plans/options
     * Obtener opciones para selects (UI)
     */
    public function options(): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->ordered()
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'label' => $plan->display_name,
                    'price' => $plan->price,
                    'currency_symbol' => $plan->currency?->symbol ?? '$',
                    'is_free' => $plan->isFree(),
                ];
            });

        return $this->successResponse($plans);
    }

    /**
     * GET /api/subscription-plans/by-slug/{slug}
     * Obtener un plan por su slug
     */
    public function getBySlug(string $slug): JsonResponse
    {
        $plan = SubscriptionPlan::with('currency')
            ->bySlug($slug)
            ->first();

        if (!$plan) {
            return $this->notFoundResponse('Plan de suscripción no encontrado');
        }

        return $this->successResponse($plan);
    }

    /**
     * POST /api/subscription-plans
     * Crear un nuevo plan de suscripción (solo super_admin)
     */
    public function store(StoreSubscriptionPlanRequest $request): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSubscriptionPlans()) {
            return $this->forbiddenResponse('No tienes permisos para crear planes de suscripción');
        }

        $data = $request->validated();

        // Generar slug si no se proporcionó
        if (empty($data['slug'])) {
            $data['slug'] = \Str::slug($data['name']);
        }

        // Verificar que el slug sea único
        if (SubscriptionPlan::where('slug', $data['slug'])->exists()) {
            return $this->errorResponse('El slug ya está en uso', 422);
        }

        // Asegurar que features sea un array
        if (isset($data['features']) && is_string($data['features'])) {
            $data['features'] = array_map('trim', explode(',', $data['features']));
        }

        $plan = SubscriptionPlan::create($data);

        // ✅ Auditoría
        $this->logCreated('SubscriptionPlan', $plan->id, $plan->toArray());

        return $this->createdResponse($plan->fresh('currency'), 'Plan de suscripción creado exitosamente');
    }

    /**
     * PUT /api/subscription-plans/{id}
     * Actualizar un plan de suscripción (solo super_admin)
     */
    public function update(UpdateSubscriptionPlanRequest $request, int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSubscriptionPlans()) {
            return $this->forbiddenResponse('No tienes permisos para actualizar planes de suscripción');
        }

        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            return $this->notFoundResponse('Plan de suscripción no encontrado');
        }

        $oldData = $plan->toArray();
        $data = $request->validated();

        // Si se actualiza el slug, verificar que sea único
        if (isset($data['slug']) && $data['slug'] !== $plan->slug) {
            if (SubscriptionPlan::where('slug', $data['slug'])->where('id', '!=', $id)->exists()) {
                return $this->errorResponse('El slug ya está en uso', 422);
            }
        }

        // Asegurar que features sea un array
        if (isset($data['features']) && is_string($data['features'])) {
            $data['features'] = array_map('trim', explode(',', $data['features']));
        }

        $plan->update($data);

        // ✅ Auditoría
        $this->logUpdated('SubscriptionPlan', $id, $oldData, $plan->fresh()->toArray());

        return $this->successResponse($plan->fresh('currency'), 'Plan de suscripción actualizado exitosamente');
    }

    /**
     * DELETE /api/subscription-plans/{id}
     * Eliminar un plan de suscripción (solo super_admin)
     */
    public function destroy(int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSubscriptionPlans()) {
            return $this->forbiddenResponse('No tienes permisos para eliminar planes de suscripción');
        }

        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            return $this->notFoundResponse('Plan de suscripción no encontrado');
        }

        // Verificar si tiene empresas asociadas
        if ($plan->companies()->exists()) {
            return $this->errorResponse(
                'No se puede eliminar el plan porque tiene empresas asociadas. 
                 Primero asigna otro plan a esas empresas.',
                422
            );
        }

        $oldData = $plan->toArray();
        $plan->delete();

        // ✅ Auditoría
        $this->logDeleted('SubscriptionPlan', $id, $oldData);

        return $this->deletedResponse('Plan de suscripción eliminado exitosamente');
    }

    /**
     * POST /api/subscription-plans/{id}/toggle
     * Activar/desactivar un plan de suscripción
     */
    public function toggle(int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSubscriptionPlans()) {
            return $this->forbiddenResponse('No tienes permisos para gestionar planes de suscripción');
        }

        $plan = SubscriptionPlan::find($id);

        if (!$plan) {
            return $this->notFoundResponse('Plan de suscripción no encontrado');
        }

        // No permitir desactivar el plan Free si está en uso
        if ($plan->slug === 'free' && $plan->is_active) {
            return $this->errorResponse('No se puede desactivar el plan Free', 422);
        }

        $oldData = $plan->toArray();
        $plan->update(['is_active' => !$plan->is_active]);

        // ✅ Auditoría
        $this->logUpdated('SubscriptionPlan', $id, $oldData, $plan->fresh()->toArray());

        $status = $plan->is_active ? 'activado' : 'desactivado';

        return $this->successResponse($plan, "Plan de suscripción {$status} exitosamente");
    }

    /**
     * GET /api/subscription-plans/public
     * Listar planes públicos (para registro de empresas)
     * No requiere autenticación
     */
    public function publicList(): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->ordered()
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'currency_symbol' => $plan->currency?->symbol ?? '$',
                    'is_free' => $plan->isFree(),
                    'formatted_price' => $plan->formatted_price,
                    'max_users' => $plan->max_users,
                    'max_bank_accounts' => $plan->max_bank_accounts,
                    'max_transactions_per_month' => $plan->max_transactions_per_month,
                    'features' => $plan->features,
                    'features_list' => $plan->features_list,
                ];
            });

        return $this->successResponse($plans);
    }

    /**
     * Verificar permisos para gestionar planes de suscripción
     */
    private function canManageSubscriptionPlans(): bool
    {
        $user = auth()->user();
        return $user && $user->hasRole('super_admin');
    }
}