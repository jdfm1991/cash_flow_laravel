<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ExchangeRate extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'exchange_rates';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'from_currency_id',
        'to_currency_id',
        'rate',
        'effective_date',
        'source',
        'notes',
        'created_by',
        'is_current',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'rate' => 'decimal:8',
        'effective_date' => 'date',
        'is_current' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    // ================================================================
    // FUENTES DE TASA
    // ================================================================

    const SOURCE_MANUAL = 'manual';
    const SOURCE_API = 'api';
    const SOURCE_SYSTEM = 'system';

    /**
     * Get all sources with labels.
     */
    public static function getSources(): array
    {
        return [
            self::SOURCE_MANUAL => 'Manual',
            self::SOURCE_API => 'API',
            self::SOURCE_SYSTEM => 'Sistema',
        ];
    }

    // ================================================================
    // RELACIONES
    // ================================================================

    /**
     * Get the source currency.
     */
    public function fromCurrency()
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    /**
     * Get the target currency.
     */
    public function toCurrency()
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }

    /**
     * Get the user who created this exchange rate.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ================================================================
    // SCOPES
    // ================================================================

    /**
     * Scope a query to only include current rates.
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope a query to get rates for a specific currency pair.
     */
    public function scopeForCurrencies($query, int $fromId, int $toId)
    {
        return $query->where('from_currency_id', $fromId)
            ->where('to_currency_id', $toId);
    }

    /**
     * Scope a query to get rates on or before a specific date.
     */
    public function scopeOnOrBefore($query, string $date)
    {
        return $query->where('effective_date', '<=', $date);
    }

    /**
     * Scope a query to get rates by source.
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    // ================================================================
    // MÉTODOS DE NEGOCIO (Tomados del sistema actual, mejorados)
    // ================================================================

    /**
     * Obtener tasa de cambio para una fecha específica
     * Equivalente a getRate() del sistema actual
     */
    public function getRate(int $fromCurrencyId, int $toCurrencyId, ?string $date = null): ?float
    {
        if ($fromCurrencyId === $toCurrencyId) {
            return 1.0;
        }

        $date = $date ?? now()->toDateString();

        $rate = $this->forCurrencies($fromCurrencyId, $toCurrencyId)
            ->onOrBefore($date)
            ->orderBy('effective_date', 'desc')
            ->first();

        return $rate ? (float) $rate->rate : null;
    }

    /**
     * Obtener todas las tasas de cambio por fecha
     * Equivalente a getRatesByDate() del sistema actual
     */
    public function getRatesByDate(string $date)
    {
        return $this->whereDate('effective_date', $date)
            ->with(['fromCurrency', 'toCurrency'])
            ->orderBy('from_currency_id')
            ->orderBy('to_currency_id')
            ->get()
            ->map(function ($rate) {
                return [
                    'id' => $rate->id,
                    'from_currency_code' => $rate->fromCurrency?->code,
                    'from_currency_name' => $rate->fromCurrency?->name,
                    'to_currency_code' => $rate->toCurrency?->code,
                    'to_currency_name' => $rate->toCurrency?->name,
                    'rate' => $rate->rate,
                    'effective_date' => $rate->effective_date,
                    'source' => $rate->source,
                ];
            });
    }

    /**
     * Convertir monto entre monedas
     * Equivalente a convert() del sistema actual
     */
    public function convert(float $amount, int $fromCurrencyId, int $toCurrencyId, ?string $date = null): array
    {
        if ($fromCurrencyId === $toCurrencyId) {
            return [
                'amount' => $amount,
                'rate' => 1.0,
                'converted_amount' => $amount,
            ];
        }

        $rate = $this->getRate($fromCurrencyId, $toCurrencyId, $date);

        if (!$rate) {
            return [
                'amount' => $amount,
                'rate' => null,
                'converted_amount' => null,
                'error' => 'No se encontró tasa de cambio',
            ];
        }

        return [
            'amount' => $amount,
            'rate' => $rate,
            'converted_amount' => round($amount * $rate, 2),
        ];
    }

    /**
     * Obtener todas las tasas de cambio (la más reciente por cada par de monedas)
     * Equivalente a getAllLatestRates() del sistema actual
     */
    public function getAllLatestRates()
    {
        // Usar una subconsulta para obtener la fecha más reciente por par
        $subQuery = $this->select('from_currency_id', 'to_currency_id')
            ->selectRaw('MAX(effective_date) as max_date')
            ->groupBy('from_currency_id', 'to_currency_id');

        return $this->joinSub($subQuery, 'latest', function ($join) {
            $join->on('exchange_rates.from_currency_id', '=', 'latest.from_currency_id')
                ->on('exchange_rates.to_currency_id', '=', 'latest.to_currency_id')
                ->on('exchange_rates.effective_date', '=', 'latest.max_date');
        })
        ->with(['fromCurrency', 'toCurrency'])
        ->orderBy('from_currency_id')
        ->orderBy('to_currency_id')
        ->get()
        ->map(function ($rate) {
            return [
                'id' => $rate->id,
                'from_currency_code' => $rate->fromCurrency?->code,
                'from_currency_name' => $rate->fromCurrency?->name,
                'to_currency_code' => $rate->toCurrency?->code,
                'to_currency_name' => $rate->toCurrency?->name,
                'rate' => $rate->rate,
                'effective_date' => $rate->effective_date,
                'source' => $rate->source,
            ];
        });
    }

    /**
     * Contar tasas de cambio que usan una moneda específica
     * Equivalente a countByCurrency() del sistema actual
     */
    public function countByCurrency(int $currencyId): int
    {
        return $this->where('from_currency_id', $currencyId)
            ->orWhere('to_currency_id', $currencyId)
            ->count();
    }

    /**
     * Obtener todas las tasas de cambio (histórico completo)
     * Equivalente a getAllRates() del sistema actual
     */
    public function getAllRates()
    {
        return $this->with(['fromCurrency', 'toCurrency'])
            ->orderBy('effective_date', 'desc')
            ->orderBy('from_currency_id')
            ->orderBy('to_currency_id')
            ->get()
            ->map(function ($rate) {
                return [
                    'id' => $rate->id,
                    'from_currency_code' => $rate->fromCurrency?->code,
                    'from_currency_name' => $rate->fromCurrency?->name,
                    'to_currency_code' => $rate->toCurrency?->code,
                    'to_currency_name' => $rate->toCurrency?->name,
                    'rate' => $rate->rate,
                    'effective_date' => $rate->effective_date,
                    'source' => $rate->source,
                ];
            });
    }

    /**
     * Buscar tasa de cambio por monedas y fecha específica
     * Equivalente a findByCurrenciesAndDate() del sistema actual
     */
    public function findByCurrenciesAndDate(int $fromCurrencyId, int $toCurrencyId, string $date): ?self
    {
        return $this->forCurrencies($fromCurrencyId, $toCurrencyId)
            ->whereDate('effective_date', $date)
            ->first();
    }

    /**
     * Obtener tasas históricas para un período
     * Equivalente a getHistoricalRates() del sistema actual
     */
    public function getHistoricalRates(int $fromCurrencyId, int $toCurrencyId, ?string $startDate, ?string $endDate)
    {
        $query = $this->forCurrencies($fromCurrencyId, $toCurrencyId);

        if ($startDate) {
            $query->whereDate('effective_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('effective_date', '<=', $endDate);
        }

        return $query->orderBy('effective_date', 'asc')
            ->get()
            ->map(function ($rate) {
                $rate->rate = (float) $rate->rate;
                return $rate;
            });
    }

    // ================================================================
    // MÉTODOS DE AYUDA
    // ================================================================

    /**
     * Get the inverse rate (to_currency → from_currency).
     */
    public function getInverseRateAttribute(): float
    {
        if ($this->rate == 0) {
            return 0;
        }
        return 1 / $this->rate;
    }

    /**
     * Get the source label.
     */
    public function getSourceLabelAttribute(): string
    {
        return self::getSources()[$this->source] ?? $this->source;
    }

    /**
     * Get the full description of the exchange rate.
     */
    public function getDescriptionAttribute(): string
    {
        return "{$this->fromCurrency?->code} → {$this->toCurrency?->code}: {$this->rate} ({$this->effective_date})";
    }

    /**
     * Check if this is the current rate for the pair.
     */
    public function isCurrent(): bool
    {
        return (bool) $this->is_current;
    }

    /**
     * Get the rate in a human-readable format.
     */
    public function getFormattedRateAttribute(): string
    {
        return number_format($this->rate, 8, '.', ',');
    }

    /**
     * Check if the rate is valid (greater than 0).
     */
    public function isValid(): bool
    {
        return $this->rate > 0;
    }

    /**
     * Get the difference between this rate and another rate.
     */
    public function getDifference(ExchangeRate $other): float
    {
        return abs($this->rate - $other->rate);
    }

    /**
     * Get the percentage difference between this rate and another rate.
     */
    public function getPercentageDifference(ExchangeRate $other): float
    {
        if ($other->rate == 0) {
            return 0;
        }
        return ($this->getDifference($other) / $other->rate) * 100;
    }

    /**
     * Invalidar caché de tasas.
     */
    public static function clearCache(): void
    {
        Cache::forget('currency_base');
        Cache::forget('currency_default');
        Cache::flush(); // O más específico si usas prefijos
    }

    // ================================================================
    // OBSERVERS (invalidar caché al guardar/actualizar)
    // ================================================================

    protected static function booted()
    {
        static::saved(function () {
            self::clearCache();
        });

        static::deleted(function () {
            self::clearCache();
        });
    }
}