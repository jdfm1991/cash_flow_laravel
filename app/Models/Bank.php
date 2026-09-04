<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Bank extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'banks';

    /**
     * The attributes that are mass assignable.
     * 
     * Mapeo de campos del sistema actual a Laravel:
     * - country → country_code (más preciso)
     * - logo → logo_path (Laravel standard)
     */
    protected $fillable = [
        'name',          // ✅ Nombre del banco
        'code',          // ✅ Código bancario (ej: 0182)
        'country_code',  // ⚠️ Cambio: country → country_code
        'website',       // ✅ Sitio web
        'phone',         // ✅ Teléfono
        'logo_path',     // ⚠️ Cambio: logo → logo_path
        'is_active',     // ✅ Activo/Inactivo
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    // ================================================================
    // RELACIONES
    // ================================================================

    /**
     * Get the bank accounts associated with this bank.
     */
    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class);
    }

    /**
     * Get the companies that have accounts in this bank.
     */
    public function companies()
    {
        return $this->hasManyThrough(Company::class, BankAccount::class);
    }

    // ================================================================
    // SCOPES (Filtros reutilizables)
    // ================================================================

    /**
     * Scope a query to only include active banks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to find a bank by its code.
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }

    /**
     * Scope a query to find banks in a specific country.
     */
    public function scopeByCountry($query, string $countryCode)
    {
        return $query->where('country_code', strtoupper($countryCode));
    }

    /**
     * Scope a query to order banks by name.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    // ================================================================
    // MÉTODOS DE NEGOCIO (Tomados del sistema actual, mejorados)
    // ================================================================

    /**
     * Obtener bancos activos (catálogo global)
     * Equivalente al método getActiveBanks() del sistema actual
     */
    public function getActiveBanks()
    {
        return $this->active()
            ->ordered()
            ->get();
    }

    /**
     * Buscar banco por código
     * Equivalente al método findByCode() del sistema actual
     */
    public function findByCode(string $code): ?self
    {
        return $this->byCode($code)->first();
    }

    /**
     * Buscar banco por nombre
     * Equivalente al método findByName() del sistema actual
     */
    public function findByName(string $name): ?self
    {
        return $this->where('name', $name)->first();
    }

    /**
     * Buscar o crear banco por código
     */
    public function findOrCreateByCode(string $code, array $data = []): self
    {
        $bank = $this->findByCode($code);

        if (!$bank) {
            $bank = $this->create(array_merge($data, ['code' => $code]));
        }

        return $bank;
    }

    /**
     * Obtener bancos por país (con caché)
     */
    public function getBanksByCountry(string $countryCode)
    {
        $cacheKey = "banks_country_{$countryCode}";

        return Cache::remember($cacheKey, 3600, function () use ($countryCode) {
            return $this->byCountry($countryCode)
                ->active()
                ->ordered()
                ->get();
        });
    }

    /**
     * Invalidar caché de bancos
     */
    public static function clearCache(): void
    {
        Cache::forget('banks_country_*');
    }

    // ================================================================
    // MÉTODOS DE AYUDA
    // ================================================================

    /**
     * Check if this bank is active.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Get the full name with code.
     */
    public function getFullNameAttribute(): string
    {
        return $this->code ? "{$this->name} ({$this->code})" : $this->name;
    }

    /**
     * Get the logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return asset('storage/' . $this->logo_path);
    }

    /**
     * Get the country name.
     * (Si tenemos una tabla countries, esto sería una relación)
     */
    public function getCountryNameAttribute(): string
    {
        $countries = [
            'VE' => 'Venezuela',
            'CO' => 'Colombia',
            'US' => 'Estados Unidos',
            'MX' => 'México',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'PE' => 'Perú',
            'ES' => 'España',
        ];

        return $countries[$this->country_code] ?? $this->country_code ?? 'Desconocido';
    }

    /**
     * Get the display name for the bank.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->getFullNameAttribute();
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