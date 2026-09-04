<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Currency\StoreCurrencyRequest;
use App\Http\Requests\Currency\UpdateCurrencyRequest;
use App\Services\CurrencyService;
use App\Models\Currency;
use App\Traits\HasCompanyContext;
use App\Traits\LogsActivity;
use App\Traits\HandlesApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    use HasCompanyContext, LogsActivity, HandlesApiResponses;

    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    /**
     * GET /api/currencies
     * Listar monedas
     */
    public function index(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('all') && 
            auth()->user()->hasRole('super_admin');

        $currencies = $includeInactive 
            ? Currency::all() 
            : Currency::active()->get();

        return $this->successResponse($currencies);
    }

    /**
     * GET /api/currencies/{id}
     * Obtener moneda específica
     */
    public function show(int $id): JsonResponse
    {
        $currency = Currency::find($id);

        if (!$currency) {
            return $this->notFoundResponse('Moneda no encontrada');
        }

        return $this->successResponse($currency);
    }

    /**
     * GET /api/currencies/base
     * Obtener moneda base del sistema
     */
    public function getBase(): JsonResponse
    {
        $baseCurrency = $this->currencyService->getBaseCurrency();

        if (!$baseCurrency) {
            return $this->notFoundResponse('No hay moneda base configurada');
        }

        return $this->successResponse($baseCurrency);
    }

    /**
     * GET /api/currencies/default
     * Obtener moneda por defecto del sistema
     */
    public function getDefault(): JsonResponse
    {
        $defaultCurrency = $this->currencyService->getDefaultCurrency();

        if (!$defaultCurrency) {
            return $this->notFoundResponse('No hay moneda por defecto configurada');
        }

        return $this->successResponse($defaultCurrency);
    }

    /**
     * GET /api/currencies/options
     * Obtener opciones para select
     */
    public function options(): JsonResponse
    {
        $currencies = Currency::active()
            ->orderBy('is_base', 'desc')
            ->orderBy('is_default', 'desc')
            ->orderBy('code')
            ->get()
            ->map(function ($currency) {
                return [
                    'id' => $currency->id,
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'is_base' => $currency->is_base,
                    'is_default' => $currency->is_default,
                    'label' => "{$currency->code} - {$currency->name} ({$currency->symbol})",
                ];
            });

        return $this->successResponse($currencies);
    }

    /**
     * POST /api/currencies
     * Crear nueva moneda (solo super_admin)
     */
    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageCurrencies()) {
            return $this->forbiddenResponse('No tienes permisos para crear monedas');
        }

        $data = $request->validated();

        $currency = Currency::create([
            'code' => strtoupper($data['code']),
            'name' => $data['name'],
            'symbol' => $data['symbol'],
            'decimal_places' => $data['decimal_places'] ?? 2,
            'is_base' => $data['is_base'] ?? false,
            'is_default' => $data['is_default'] ?? false,
            'is_active' => true,
        ]);

        // Si es moneda base, desmarcar otras
        if ($currency->is_base) {
            Currency::where('id', '!=', $currency->id)
                ->update(['is_base' => false]);
        }

        // Si es moneda por defecto, desmarcar otras
        if ($currency->is_default) {
            Currency::where('id', '!=', $currency->id)
                ->update(['is_default' => false]);
        }

        // Invalidar caché
        Currency::clearCache();

        // ✅ Auditoría
        $this->logCreated('Currency', $currency->id, $currency->toArray());

        return $this->createdResponse($currency, 'Moneda creada exitosamente');
    }

    /**
     * PUT /api/currencies/{id}
     * Actualizar moneda (solo super_admin)
     */
    public function update(UpdateCurrencyRequest $request, int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageCurrencies()) {
            return $this->forbiddenResponse('No tienes permisos para actualizar monedas');
        }

        $currency = Currency::find($id);

        if (!$currency) {
            return $this->notFoundResponse('Moneda no encontrada');
        }

        $oldData = $currency->toArray();
        $data = $request->validated();

        // Si se está estableciendo como base
        if (isset($data['is_base']) && $data['is_base']) {
            Currency::where('id', '!=', $id)
                ->update(['is_base' => false]);
        }

        // Si se está estableciendo como default
        if (isset($data['is_default']) && $data['is_default']) {
            Currency::where('id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $currency->update($data);

        // Invalidar caché
        Currency::clearCache();

        // ✅ Auditoría
        $this->logUpdated('Currency', $id, $oldData, $currency->fresh()->toArray());

        return $this->successResponse($currency->fresh(), 'Moneda actualizada exitosamente');
    }

    /**
     * DELETE /api/currencies/{id}
     * Eliminar moneda (solo super_admin)
     */
    public function destroy(int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageCurrencies()) {
            return $this->forbiddenResponse('No tienes permisos para eliminar monedas');
        }

        $currency = Currency::find($id);

        if (!$currency) {
            return $this->notFoundResponse('Moneda no encontrada');
        }

        // No permitir eliminar la moneda base
        if ($currency->is_base) {
            return $this->errorResponse('No se puede eliminar la moneda base del sistema', 422);
        }

        // Verificar si tiene transacciones
        $hasTransactions = \App\Models\Transaction::where('currency_id', $id)->exists();
        if ($hasTransactions) {
            return $this->errorResponse('No se puede eliminar la moneda porque tiene transacciones asociadas', 422);
        }

        // Verificar si tiene tasas de cambio
        $hasExchangeRates = \App\Models\ExchangeRate::where('from_currency_id', $id)
            ->orWhere('to_currency_id', $id)
            ->exists();

        if ($hasExchangeRates) {
            return $this->errorResponse('No se puede eliminar la moneda porque tiene tasas de cambio asociadas', 422);
        }

        $oldData = $currency->toArray();
        $currency->delete();

        // Invalidar caché
        Currency::clearCache();

        // ✅ Auditoría
        $this->logDeleted('Currency', $id, $oldData);

        return $this->deletedResponse('Moneda eliminada exitosamente');
    }

    /**
     * POST /api/currencies/{id}/toggle
     * Activar/desactivar moneda
     */
    public function toggle(int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageCurrencies()) {
            return $this->forbiddenResponse('No tienes permisos para gestionar monedas');
        }

        $currency = Currency::find($id);

        if (!$currency) {
            return $this->notFoundResponse('Moneda no encontrada');
        }

        // No permitir desactivar la moneda base
        if ($currency->is_base && $currency->is_active) {
            return $this->errorResponse('No se puede desactivar la moneda base del sistema', 422);
        }

        $oldData = $currency->toArray();
        $currency->update(['is_active' => !$currency->is_active]);

        // Invalidar caché
        Currency::clearCache();

        // ✅ Auditoría
        $this->logUpdated('Currency', $id, $oldData, $currency->fresh()->toArray());

        $status = $currency->is_active ? 'activada' : 'desactivada';

        return $this->successResponse($currency, "Moneda {$status} exitosamente");
    }

    /**
     * Verificar permisos para gestionar monedas
     */
    private function canManageCurrencies(): bool
    {
        $user = auth()->user();
        return $user->hasRole('super_admin');
    }
}