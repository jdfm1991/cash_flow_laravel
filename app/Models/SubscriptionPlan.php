<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'max_users',
        'max_bank_accounts',
        'max_transactions_per_month',
        'features',
        'price',
        'currency_id',
        'is_active',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'max_users' => 'integer',
        'max_bank_accounts' => 'integer',
        'max_transactions_per_month' => 'integer',
        'sort_order' => 'integer',
        'currency_id' => 'integer',
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

    /**
     * Get the currency associated with this plan.
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get the companies that have this plan.
     */
    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    // ================================================================
    // SCOPES
    // ================================================================

    /**
     * Scope a query to only include active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to find a plan by its slug.
     */
    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Scope a query to order plans by sort_order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // ================================================================
    // MÉTODOS DE AYUDA
    // ================================================================

    /**
     * Check if this plan has a specific feature.
     */
    public function hasFeature(string $feature): bool
    {
        if (!is_array($this->features)) {
            return false;
        }

        return in_array($feature, $this->features) || in_array('all', $this->features);
    }

    /**
     * Check if this plan is free (price = 0).
     */
    public function isFree(): bool
    {
        return $this->price == 0;
    }

    /**
     * Check if this plan has unlimited users.
     */
    public function hasUnlimitedUsers(): bool
    {
        return $this->max_users >= 999;
    }

    /**
     * Check if this plan has unlimited bank accounts.
     */
    public function hasUnlimitedBankAccounts(): bool
    {
        return $this->max_bank_accounts >= 999;
    }

    /**
     * Check if this plan has unlimited transactions.
     */
    public function hasUnlimitedTransactions(): bool
    {
        return $this->max_transactions_per_month >= 999999;
    }

    /**
     * Get the formatted price with currency symbol.
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->isFree()) {
            return 'Gratis';
        }

        $symbol = $this->currency?->symbol ?? '$';
        return $symbol . ' ' . number_format($this->price, 2);
    }

    /**
     * Get the features as a bullet list.
     */
    public function getFeaturesListAttribute(): string
    {
        if (empty($this->features)) {
            return 'Sin características definidas';
        }

        if (in_array('all', $this->features)) {
            return 'Todas las características disponibles';
        }

        return implode("\n", array_map(function ($feature) {
            return '• ' . ucfirst(str_replace('_', ' ', $feature));
        }, $this->features));
    }

    /**
     * Get the display name for the plan.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name . ' (' . $this->getFormattedPriceAttribute() . ')';
    }
}