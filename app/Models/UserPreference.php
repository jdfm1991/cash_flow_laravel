<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'language',
        'timezone',
        'theme',
        'date_format',
        'time_format',
        'currency_display',
        'notifications_email',
        'notifications_push',
        'notifications_in_app',
        'dashboard_layout',
        'dashboard_widgets',
        'items_per_page',
        'auto_save_reports',
        'favorite_reports',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'notifications_email' => 'boolean',
        'notifications_push' => 'boolean',
        'notifications_in_app' => 'boolean',
        'auto_save_reports' => 'boolean',
        'dashboard_layout' => 'array',
        'dashboard_widgets' => 'array',
        'favorite_reports' => 'array',
        'items_per_page' => 'integer',
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
     * Get the user that owns the preferences.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ================================================================
    // SCOPES
    // ================================================================

    /**
     * Scope a query to get preferences for a specific theme.
     */
    public function scopeByTheme($query, string $theme)
    {
        return $query->where('theme', $theme);
    }

    /**
     * Scope a query to get preferences for a specific language.
     */
    public function scopeByLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }

    // ================================================================
    // MÉTODOS DE AYUDA
    // ================================================================

    /**
     * Get the full display name for the theme.
     */
    public function getThemeLabelAttribute(): string
    {
        return [
            'light' => 'Claro',
            'dark' => 'Oscuro',
            'system' => 'Sistema',
        ][$this->theme] ?? $this->theme;
    }

    /**
     * Get the full display name for the language.
     */
    public function getLanguageLabelAttribute(): string
    {
        return [
            'es' => 'Español',
            'en' => 'Inglés',
            'pt' => 'Portugués',
        ][$this->language] ?? $this->language;
    }

    /**
     * Get the timezone offset for display.
     */
    public function getTimezoneOffsetAttribute(): string
    {
        $timezone = new \DateTimeZone($this->timezone);
        $offset = $timezone->getOffset(new \DateTime());
        $hours = floor(abs($offset) / 3600);
        $minutes = (abs($offset) % 3600) / 60;

        return ($offset >= 0 ? '+' : '-') . str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Check if the user wants email notifications.
     */
    public function wantsEmailNotifications(): bool
    {
        return $this->notifications_email && $this->user?->is_active;
    }

    /**
     * Check if the user wants push notifications.
     */
    public function wantsPushNotifications(): bool
    {
        return $this->notifications_push && $this->user?->is_active;
    }

    /**
     * Check if the user wants in-app notifications.
     */
    public function wantsInAppNotifications(): bool
    {
        return $this->notifications_in_app && $this->user?->is_active;
    }

    /**
     * Get a specific widget setting.
     */
    public function getWidgetSetting(string $widget, $default = null)
    {
        if (!$this->dashboard_widgets || !isset($this->dashboard_widgets[$widget])) {
            return $default;
        }

        return $this->dashboard_widgets[$widget];
    }

    /**
     * Set a specific widget setting.
     */
    public function setWidgetSetting(string $widget, $value): self
    {
        $widgets = $this->dashboard_widgets ?? [];
        $widgets[$widget] = $value;
        $this->dashboard_widgets = $widgets;

        return $this;
    }

    /**
     * Get the formatted date for display.
     */
    public function formatDate(\DateTimeInterface $date): string
    {
        return $date->format($this->date_format);
    }

    /**
     * Get the formatted time for display.
     */
    public function formatTime(\DateTimeInterface $time): string
    {
        return $time->format($this->time_format);
    }

    /**
     * Get the available date formats.
     */
    public static function getAvailableDateFormats(): array
    {
        return [
            'Y-m-d' => 'YYYY-MM-DD (2026-07-10)',
            'd/m/Y' => 'DD/MM/YYYY (10/07/2026)',
            'm/d/Y' => 'MM/DD/YYYY (07/10/2026)',
            'd-m-Y' => 'DD-MM-YYYY (10-07-2026)',
            'm-d-Y' => 'MM-DD-YYYY (07-10-2026)',
            'F j, Y' => 'July 10, 2026',
            'j M Y' => '10 Jul 2026',
        ];
    }

    /**
     * Get the available time formats.
     */
    public static function getAvailableTimeFormats(): array
    {
        return [
            'H:i' => '24h (14:30)',
            'H:i:s' => '24h con segundos (14:30:00)',
            'h:i A' => '12h (02:30 PM)',
            'h:i:s A' => '12h con segundos (02:30:00 PM)',
        ];
    }

    /**
     * Get the available themes.
     */
    public static function getAvailableThemes(): array
    {
        return [
            'light' => 'Claro',
            'dark' => 'Oscuro',
            'system' => 'Sistema',
        ];
    }

    /**
     * Get the available languages.
     */
    public static function getAvailableLanguages(): array
    {
        return [
            'es' => 'Español',
            'en' => 'English',
            'pt' => 'Português',
        ];
    }
}