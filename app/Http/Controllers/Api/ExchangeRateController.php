<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExchangeRate\StoreExchangeRateRequest;
use App\Http\Requests\ExchangeRate\UpdateExchangeRateRequest;
use App\Services\CurrencyService;
use App\Models\ExchangeRate;
use App\Models\Currency;
use App\Traits\HasCompanyContext;
use App\Traits\LogsActivity;
use App\Traits\HandlesApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExchangeRateController extends Controller
{
    use HasCompanyContext, LogsActivity, HandlesApiResponses;

    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    /**
     * GET /api/exchange-rates
     */
    public function index(Request $request): JsonResponse
    {
        $fromCurrency = $request->from;
        $toCurrency = $request->to;
        $date = $request->date;

        if ($fromCurrency && $toCurrency) {
            $rateDate = $date ?? now()->toDateString();
            $rate = $this->currencyService->getRate((int) $fromCurrency, (int) $toCurrency, $rateDate);

            return $this->successResponse([
                'from_currency_id' => (int) $fromCurrency,
                'to_currency_id' => (int) $toCurrency,
                'date' => $rateDate,
                'rate' => $rate,
            ]);
        }

        if ($date) {
            $rates = ExchangeRate::whereDate('effective_date', $date)
                ->with(['fromCurrency', 'toCurrency'])
                ->get()
                ->map(function ($rate) {
                    return [
                        'id' => $rate->id,
                        'from_currency_code' => $rate->fromCurrency?->code,
                        'to_currency_code' => $rate->toCurrency?->code,
                        'rate' => (float) $rate->rate,
                        'effective_date' => $rate->effective_date,
                    ];
                });

            return $this->successResponse($rates);
        }

        $rates = ExchangeRate::with(['fromCurrency', 'toCurrency'])
            ->where('is_current', true)
            ->orderBy('from_currency_id')
            ->orderBy('to_currency_id')
            ->get()
            ->map(function ($rate) {
                return [
                    'id' => $rate->id,
                    'from_currency_code' => $rate->fromCurrency?->code,
                    'to_currency_code' => $rate->toCurrency?->code,
                    'rate' => (float) $rate->rate,
                    'effective_date' => $rate->effective_date,
                    'is_current' => $rate->is_current,
                ];
            });

        return $this->successResponse($rates);
    }

    /**
     * GET /api/exchange-rates/latest
     */
    public function latest(Request $request): JsonResponse
    {
        $fromCurrency = $request->from;
        $toCurrency = $request->to;

        if (!$fromCurrency || !$toCurrency) {
            $base = $this->currencyService->getBaseCurrency();
            $default = $this->currencyService->getDefaultCurrency();
            $fromCurrency = $base?->id;
            $toCurrency = $default?->id;
        }

        if (!$fromCurrency || !$toCurrency) {
            return $this->notFoundResponse('No se encontraron monedas configuradas');
        }

        $rate = $this->currencyService->getRate((int) $fromCurrency, (int) $toCurrency);

        return $this->successResponse([
            'from_currency_id' => (int) $fromCurrency,
            'to_currency_id' => (int) $toCurrency,
            'rate' => $rate,
            'date' => now()->toDateString(),
        ]);
    }

    /**
     * GET /api/exchange-rates/historical
     */
    public function historical(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|exists:currencies,id',
            'to' => 'required|exists:currencies,id',
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
        ]);

        $query = ExchangeRate::where('from_currency_id', $request->from)
            ->where('to_currency_id', $request->to);

        if ($request->start) {
            $query->where('effective_date', '>=', $request->start);
        }

        if ($request->end) {
            $query->where('effective_date', '<=', $request->end);
        }

        $rates = $query->orderBy('effective_date', 'asc')
            ->get()
            ->map(function ($rate) {
                return [
                    'id' => $rate->id,
                    'rate' => (float) $rate->rate,
                    'effective_date' => $rate->effective_date,
                ];
            });

        return $this->successResponse($rates);
    }

    /**
     * GET /api/exchange-rates/{id}
     */
    public function show(int $id): JsonResponse
    {
        $rate = ExchangeRate::with(['fromCurrency', 'toCurrency'])
            ->find($id);

        if (!$rate) {
            return $this->notFoundResponse('Tasa de cambio no encontrada');
        }

        return $this->successResponse([
            'id' => $rate->id,
            'from_currency_id' => $rate->from_currency_id,
            'from_currency_code' => $rate->fromCurrency?->code,
            'to_currency_id' => $rate->to_currency_id,
            'to_currency_code' => $rate->toCurrency?->code,
            'rate' => (float) $rate->rate,
            'effective_date' => $rate->effective_date,
            'source' => $rate->source,
            'notes' => $rate->notes,
            'is_current' => $rate->is_current,
            'inverse_rate' => $rate->inverse_rate,
        ]);
    }

    /**
     * POST /api/exchange-rates
     */
    public function store(StoreExchangeRateRequest $request): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageExchangeRates()) {
            return $this->forbiddenResponse('No tienes permisos para crear tasas de cambio');
        }

        $data = $request->validated();
        $data['created_by'] = auth()->id();

        $rate = ExchangeRate::create($data);

        if ($rate->is_current) {
            ExchangeRate::where('from_currency_id', $rate->from_currency_id)
                ->where('to_currency_id', $rate->to_currency_id)
                ->where('id', '!=', $rate->id)
                ->update(['is_current' => false]);
        }

        $this->currencyService->invalidateCache(
            $rate->from_currency_id,
            $rate->to_currency_id
        );

        // ✅ Auditoría
        $this->logCreated('ExchangeRate', $rate->id, $rate->toArray());

        return $this->createdResponse(
            $rate->load(['fromCurrency', 'toCurrency']),
            'Tasa de cambio creada exitosamente'
        );
    }

    /**
     * PUT /api/exchange-rates/{id}
     */
    public function update(UpdateExchangeRateRequest $request, int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageExchangeRates()) {
            return $this->forbiddenResponse('No tienes permisos para actualizar tasas de cambio');
        }

        $rate = ExchangeRate::find($id);

        if (!$rate) {
            return $this->notFoundResponse('Tasa de cambio no encontrada');
        }

        $oldData = $rate->toArray();
        $rate->update($request->validated());

        $this->currencyService->invalidateCache(
            $rate->from_currency_id,
            $rate->to_currency_id
        );

        // ✅ Auditoría
        $this->logUpdated('ExchangeRate', $id, $oldData, $rate->fresh()->toArray());

        return $this->successResponse(
            $rate->fresh()->load(['fromCurrency', 'toCurrency']),
            'Tasa de cambio actualizada exitosamente'
        );
    }

    /**
     * DELETE /api/exchange-rates/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageExchangeRates()) {
            return $this->forbiddenResponse('No tienes permisos para eliminar tasas de cambio');
        }

        $rate = ExchangeRate::find($id);

        if (!$rate) {
            return $this->notFoundResponse('Tasa de cambio no encontrada');
        }

        $fromId = $rate->from_currency_id;
        $toId = $rate->to_currency_id;
        $oldData = $rate->toArray();

        $rate->delete();

        $this->currencyService->invalidateCache($fromId, $toId);

        // ✅ Auditoría
        $this->logDeleted('ExchangeRate', $id, $oldData);

        return $this->deletedResponse('Tasa de cambio eliminada exitosamente');
    }

    /**
     * GET /api/exchange-rates/convert
     */
    public function convert(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'from' => 'required|exists:currencies,id',
            'to' => 'required|exists:currencies,id',
            'date' => 'nullable|date',
        ]);

        $result = $this->currencyService->convert(
            (float) $request->amount,
            (int) $request->from,
            (int) $request->to,
            $request->date
        );

        return $this->successResponse([
            'amount' => (float) $request->amount,
            'from_currency_id' => (int) $request->from,
            'to_currency_id' => (int) $request->to,
            'converted_amount' => $result,
            'date' => $request->date ?? now()->toDateString(),
        ]);
    }

    /**
     * GET /api/exchange-rates/currencies
     */
    public function currencies(): JsonResponse
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
                ];
            });

        return $this->successResponse($currencies);
    }

    /**
     * Verificar permisos para gestionar tasas de cambio
     */
    private function canManageExchangeRates(): bool
    {
        $user = auth()->user();
        return $user->hasRole('super_admin') ||
               $user->hasPermissionTo('manage_exchange_rates');
    }
}