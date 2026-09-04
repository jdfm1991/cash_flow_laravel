<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Currency extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'currencies';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'is_base',
        'is_default',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'decimal_places' => 'integer',
        'is_base' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
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
    // RELACIONES
    // ================================================================

    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class);
    }

    public function exchangeRatesFrom()
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency_id');
    }

    public function exchangeRatesTo()
    {
        return $this->hasMany(ExchangeRate::class, 'to_currency_id');
    }

    public function subscriptionPlans()
    {
        return $this->hasMany(SubscriptionPlan::class);
    }

    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    // ================================================================
    // SCOPES (Filtros reutilizables)
    // ================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBase($query)
    {
        return $query->where('is_base', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }

    // ================================================================
    // MÉTODOS DE NEGOCIO (Tomados del sistema actual, mejorados)
    // ================================================================

    /**
     * Obtener moneda base del sistema (con caché)
     * Equivalente al método getBaseCurrency() del sistema actual
     */
    public function getBaseCurrency(): ?self
    {
        return Cache::remember('currency_base', 3600, function () {
            return $this->where('is_base', true)
                ->where('is_active', true)
                ->first();
        });
    }

    /**
     * Obtener moneda por defecto (con caché)
     * Equivalente al método getDefaultCurrency() del sistema actual
     */
    public function getDefaultCurrency(): ?self
    {
        return Cache::remember('currency_default', 3600, function () {
            $currency = $this->where('is_default', true)
                ->where('is_active', true)
                ->first();

            // Si no hay moneda default, usar la base como fallback
            if (!$currency) {
                return $this->getBaseCurrency();
            }

            return $currency;
        });
    }

    /**
     * Obtener todas las monedas activas
     * Equivalente al método getActiveCurrencies() del sistema actual
     */
    public function getActiveCurrencies()
    {
        return $this->active()
            ->orderBy('is_default', 'desc')
            ->orderBy('is_base', 'desc')
            ->orderBy('code')
            ->get();
    }

    /**
     * Obtener todas las monedas
     * Equivalente al método getAllCurrencies() del sistema actual
     */
    public function getAllCurrencies()
    {
        return $this->orderBy('is_default', 'desc')
            ->orderBy('is_base', 'desc')
            ->orderBy('code')
            ->get();
    }

    /**
     * Buscar moneda por código
     * Equivalente al método findByCode() del sistema actual
     */
    public function findByCode(string $code): ?self
    {
        return $this->byCode($code)->first();
    }

    /**
     * Verificar si existe moneda base
     * Equivalente al método hasBaseCurrency() del sistema actual
     */
    public function hasBaseCurrency(): bool
    {
        return $this->where('is_base', true)->exists();
    }

    /**
     * Verificar si existe moneda por defecto
     * Equivalente al método hasDefaultCurrency() del sistema actual
     */
    public function hasDefaultCurrency(): bool
    {
        return $this->where('is_default', true)->exists();
    }

    /**
     * Establecer moneda base (quitar base de otras y establecer esta)
     * Equivalente al método setAsBaseCurrency() del sistema actual
     */
    public function setAsBaseCurrency(int $currencyId): bool
    {
        try {
            DB::transaction(function () use ($currencyId) {
                // Quitar base de todas las monedas
                $this->where('is_base', true)->update(['is_base' => false]);
                
                // Establecer nueva moneda base
                $this->where('id', $currencyId)->update(['is_base' => true]);
            });

            // Invalidar caché
            Cache::forget('currency_base');
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Establecer moneda por defecto
     * Equivalente al método setAsDefaultCurrency() del sistema actual
     */
    public function setAsDefaultCurrency(int $currencyId): bool
    {
        try {
            DB::transaction(function () use ($currencyId) {
                // Quitar default de todas las monedas
                $this->where('is_default', true)->update(['is_default' => false]);
                
                // Establecer nueva moneda default
                $this->where('id', $currencyId)->update(['is_default' => true]);
            });

            // Invalidar caché
            Cache::forget('currency_default');
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ================================================================
    // MÉTODOS DE AYUDA
    // ================================================================

    /**
     * Formatear un monto con esta moneda
     */
    public function formatAmount(float $amount): string
    {
        return number_format($amount, $this->decimal_places, '.', ',');
    }

    /**
     * Formatear un monto con el símbolo de la moneda
     */
    public function formatAmountWithSymbol(float $amount): string
    {
        return $this->symbol . ' ' . $this->formatAmount($amount);
    }

    /**
     * Redondear un monto según los decimales de la moneda
     */
    public function roundAmount(float $amount): float
    {
        return round($amount, $this->decimal_places);
    }

    /**
     * Invalidar caché de monedas
     */
    public static function clearCache(): void
    {
        Cache::forget('currency_base');
        Cache::forget('currency_default');
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