<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateGeneralSettingsRequest;
use App\Http\Requests\Settings\UpdateCurrencySettingsRequest;
use App\Http\Requests\Settings\UpdateNotificationSettingsRequest;
use App\Http\Requests\Settings\UpdateSecuritySettingsRequest;
use App\Models\Currency;
use App\Models\Company;
use App\Services\CurrencyService;
use App\Traits\HasCompanyContext;
use App\Traits\LogsActivity;
use App\Traits\HandlesApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    use HasCompanyContext, LogsActivity, HandlesApiResponses;

    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    /**
     * GET /api/settings
     * Obtener todas las configuraciones del sistema
     */
    public function index(Request $request)
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSettings()) {
            return $this->forbiddenResponse('No tienes permisos para ver configuraciones');
        }

        $settings = [
            'general' => $this->getGeneralSettings(),
            'currency' => $this->getCurrencySettings(),
            'notification' => $this->getNotificationSettings(),
            'security' => $this->getSecuritySettings(),
            'company' => $this->getCompanySettings(),
        ];

        $this->logActivity(
            action: 'view_settings',
            entityType: 'Settings',
            entityId: 0,
            note: json_encode(['section' => 'all'])
        );

        return $this->successResponse($settings);
    }

    /**
     * GET /api/settings/general
     * Obtener configuraciones generales
     */
    public function getGeneral(Request $request)
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSettings()) {
            return $this->forbiddenResponse('No tienes permisos para ver configuraciones');
        }

        $this->logActivity(
            action: 'view_settings',
            entityType: 'Settings',
            entityId: 0,
            note: json_encode(['section' => 'general'])
        );

        return $this->successResponse($this->getGeneralSettings());
    }

    /**
     * PUT /api/settings/general
     * Actualizar configuraciones generales
     */
    public function updateGeneral(UpdateGeneralSettingsRequest $request)
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSettings()) {
            return $this->forbiddenResponse('No tienes permisos para actualizar configuraciones');
        }

        $settings = $request->validated();

        foreach ($settings as $key => $value) {
            $this->saveSetting('general.' . $key, $value);
        }

        Cache::forget('settings_general');

        $this->logActivity(
            action: 'update_settings',
            entityType: 'Settings',
            entityId: 0,
            note: json_encode([
                'section' => 'general',
                'fields' => array_keys($settings),
            ])
        );

        return $this->successResponse(
            $this->getGeneralSettings(),
            'Configuraciones generales actualizadas'
        );
    }

    /**
     * GET /api/settings/currency
     * Obtener configuraciones de moneda
     */
    public function getCurrency(Request $request)
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSettings()) {
            return $this->forbiddenResponse('No tienes permisos para ver configuraciones');
        }

        $this->logActivity(
            action: 'view_settings',
            entityType: 'Settings',
            entityId: 0,
            note: json_encode(['section' => 'currency'])
        );

        return $this->successResponse($this->getCurrencySettings());
    }

    /**
     * PUT /api/settings/currency
     * Actualizar configuraciones de moneda
     */
    public function updateCurrency(UpdateCurrencySettingsRequest $request)
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSettings()) {
            return $this->forbiddenResponse('No tienes permisos para actualizar configuraciones');
        }

        $data = $request->validated();
        $changes = [];

        // Actualizar moneda base
        if (isset($data['base_currency_id'])) {
            $currency = Currency::findOrFail($data['base_currency_id']);

            // Desmarcar todas las monedas como base
            Currency::where('is_base', true)->update(['is_base' => false]);

            // Marcar la nueva como base
            $currency->is_base = true;
            $currency->save();

            $changes['base_currency_id'] = $data['base_currency_id'];

            // Invalidar caché
            $this->currencyService->invalidateCache(
                $currency->id,
                $currency->id
            );
        }

        // Actualizar moneda por defecto
        if (isset($data['default_currency_id'])) {
            $currency = Currency::findOrFail($data['default_currency_id']);

            // Desmarcar todas las monedas como default
            Currency::where('is_default', true)->update(['is_default' => false]);

            // Marcar la nueva como default
            $currency->is_default = true;
            $currency->save();

            $changes['default_currency_id'] = $data['default_currency_id'];

            // Invalidar caché
            $this->currencyService->invalidateCache(
                $currency->id,
                $currency->id
            );
        }

        // Guardar configuraciones adicionales
        if (isset($data['decimal_places'])) {
            $this->saveSetting('currency.decimal_places', $data['decimal_places']);
            $changes['decimal_places'] = $data['decimal_places'];
        }

        if (isset($data['currency_display'])) {
            $this->saveSetting('currency.currency_display', $data['currency_display']);
            $changes['currency_display'] = $data['currency_display'];
        }

        // Invalidar caché general
        Cache::forget('currency_base');
        Cache::forget('currency_default');

        $this->logActivity(
            action: 'update_settings',
            entityType: 'Settings',
            entityId: 0,
            note: json_encode([
                'section' => 'currency',
                'fields' => array_keys($changes),
            ])
        );

        return $this->successResponse(
            $this->getCurrencySettings(),
            'Configuraciones de moneda actualizadas'
        );
    }

    /**
     * GET /api/settings/notification
     * Obtener configuraciones de notificaciones
     */
    public function getNotification(Request $request)
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSettings()) {
            return $this->forbiddenResponse('No tienes permisos para ver configuraciones');
        }

        $this->logActivity(
            action: 'view_settings',
            entityType: 'Settings',
            entityId: 0,
            note: json_encode(['section' => 'notification'])
        );

        return $this->successResponse($this->getNotificationSettings());
    }

    /**
     * PUT /api/settings/notification
     * Actualizar configuraciones de notificaciones
     */
    public function updateNotification(UpdateNotificationSettingsRequest $request)
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSettings()) {
            return $this->forbiddenResponse('No tienes permisos para actualizar configuraciones');
        }

        $settings = $request->validated();

        foreach ($settings as $key => $value) {
            $this->saveSetting('notification.' . $key, $value);
        }

        Cache::forget('settings_notification');

        $this->logActivity(
            action: 'update_settings',
            entityType: 'Settings',
            entityId: 0,
            note: json_encode([
                'section' => 'notification',
                'fields' => array_keys($settings),
            ])
        );

        return $this->successResponse(
            $this->getNotificationSettings(),
            'Configuraciones de notificaciones actualizadas'
        );
    }

    /**
     * GET /api/settings/security
     * Obtener configuraciones de seguridad
     */
    public function getSecurity(Request $request)
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSettings()) {
            return $this->forbiddenResponse('No tienes permisos para ver configuraciones');
        }

        $this->logActivity(
            action: 'view_settings',
            entityType: 'Settings',
            entityId: 0,
            note: json_encode(['section' => 'security'])
        );

        return $this->successResponse($this->getSecuritySettings());
    }

    /**
     * PUT /api/settings/security
     * Actualizar configuraciones de seguridad
     */
    public function updateSecurity(UpdateSecuritySettingsRequest $request)
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSettings()) {
            return $this->forbiddenResponse('No tienes permisos para actualizar configuraciones');
        }

        $settings = $request->validated();

        foreach ($settings as $key => $value) {
            $this->saveSetting('security.' . $key, $value);
        }

        Cache::forget('settings_security');

        $this->logActivity(
            action: 'update_settings',
            entityType: 'Settings',
            entityId: 0,
            note: json_encode([
                'section' => 'security',
                'fields' => array_keys($settings),
            ])
        );

        return $this->successResponse(
            $this->getSecuritySettings(),
            'Configuraciones de seguridad actualizadas'
        );
    }

    /**
     * POST /api/settings/cache/clear
     * Limpiar caché del sistema
     */
    public function clearCache(Request $request)
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSettings()) {
            return $this->forbiddenResponse('No tienes permisos para gestionar el caché');
        }

        Cache::flush();

        $this->logActivity(
            action: 'clear_cache',
            entityType: 'Settings',
            entityId: 0,
            note: json_encode(['user_id' => auth()->id()])
        );

        return $this->successResponse(null, 'Caché del sistema limpiado exitosamente');
    }

    /**
     * GET /api/settings/company
     * Obtener configuraciones de la empresa
     */
    public function getCompany(Request $request)
    {
        $companyId = $this->getCurrentCompanyId();

        $company = Company::with(['subscriptionPlan'])->find($companyId);

        if (!$company) {
            return $this->notFoundResponse('Empresa no encontrada');
        }

        $this->logActivity(
            action: 'view_settings',
            entityType: 'Settings',
            entityId: 0,
            note: json_encode(['section' => 'company', 'company_id' => $companyId])
        );

        return $this->successResponse([
            'id' => $company->id,
            'name' => $company->name,
            'business_name' => $company->business_name,
            'tax_id' => $company->tax_id,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'logo_url' => $company->logo_url,
            'theme' => $company->theme ?? 'light',
            'timezone' => $company->timezone,
            'subscription' => $company->subscriptionPlan ? [
                'id' => $company->subscriptionPlan->id,
                'name' => $company->subscriptionPlan->name,
                'max_users' => $company->subscriptionPlan->max_users,
                'max_bank_accounts' => $company->subscriptionPlan->max_bank_accounts,
                'max_transactions_per_month' => $company->subscriptionPlan->max_transactions_per_month,
                'expires_at' => $company->subscription_expires_at,
            ] : null,
            'is_active' => $company->is_active,
        ]);
    }

    /**
     * PUT /api/settings/company
     * Actualizar configuraciones de empresa
     */
    public function updateCompany(Request $request)
    {
        // ✅ Verificación directa de permisos
        if (!$this->canManageSettings()) {
            return $this->forbiddenResponse('No tienes permisos para actualizar configuraciones');
        }

        $companyId = $this->getCurrentCompanyId();
        $company = Company::find($companyId);

        if (!$company) {
            return $this->notFoundResponse('Empresa no encontrada');
        }

        $request->validate([
            'name' => 'sometimes|string|max:100',
            'business_name' => 'sometimes|string|max:200',
            'tax_id' => 'sometimes|string|max:50',
            'email' => 'sometimes|email|max:100',
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string',
            'timezone' => 'sometimes|string|max:50',
            'theme' => 'sometimes|string|in:light,dark',
        ]);

        $oldData = $company->toArray();
        $company->update($request->all());
        $company->refresh();

        $this->logActivity(
            action: 'update_settings',
            entityType: 'Company',
            entityId: $companyId,
            oldData: $oldData,
            newData: $company->toArray(),
            note: json_encode(['section' => 'company'])
        );

        return $this->successResponse(
            $this->getCompanySettings(),
            'Configuraciones de empresa actualizadas'
        );
    }

    /**
     * GET /api/settings/preferences
     * Obtener preferencias del usuario
     */
    public function getPreferences(Request $request)
    {
        $user = auth()->user();

        $preferences = $user->preferences ?? [
            'language' => 'es',
            'timezone' => 'America/Caracas',
            'theme' => 'light',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'currency_display' => 'symbol',
            'items_per_page' => 25,
            'notifications_email' => true,
            'notifications_push' => true,
            'notifications_in_app' => true,
        ];

        $this->logActivity(
            action: 'view_settings',
            entityType: 'UserPreferences',
            entityId: $user->id,
            note: json_encode(['section' => 'preferences'])
        );

        return $this->successResponse($preferences);
    }

    /**
     * PUT /api/settings/preferences
     * Actualizar preferencias del usuario
     */
    public function updatePreferences(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'language' => 'sometimes|string|in:es,en,pt',
            'timezone' => 'sometimes|string|max:50',
            'theme' => 'sometimes|string|in:light,dark,system',
            'date_format' => 'sometimes|string|max:20',
            'time_format' => 'sometimes|string|max:10',
            'currency_display' => 'sometimes|string|in:symbol,code,both',
            'items_per_page' => 'sometimes|integer|min:5|max:100',
            'notifications_email' => 'nullable|boolean',
            'notifications_push' => 'nullable|boolean',
            'notifications_in_app' => 'nullable|boolean',
        ]);

        $oldData = $user->preferences ?? [];
        $user->preferences = array_merge($oldData, $request->all());
        $user->save();

        $this->logActivity(
            action: 'update_settings',
            entityType: 'UserPreferences',
            entityId: $user->id,
            oldData: $oldData,
            newData: $user->preferences,
            note: json_encode(['section' => 'preferences'])
        );

        return $this->successResponse(
            $user->preferences,
            'Preferencias actualizadas exitosamente'
        );
    }

    // ================================================================
    // MÉTODOS PRIVADOS
    // ================================================================

    /**
     * Verificar permisos para gestionar configuraciones
     */
    protected function canManageSettings(): bool
    {
        $user = auth()->user();
        return $user->hasRole('super_admin') ||
            $user->hasRole('admin') ||
            $user->hasPermissionTo('manage_settings');
    }

    /**
     * Obtener configuraciones generales
     */
    protected function getGeneralSettings(): array
    {
        return Cache::remember('settings_general', 3600, function () {
            return [
                'app_name' => config('app.name', 'CashFlow'),
                'app_url' => config('app.url'),
                'app_locale' => config('app.locale', 'es'),
                'app_timezone' => config('app.timezone', 'America/Caracas'),
                'date_format' => $this->getSetting('general.date_format', 'Y-m-d'),
                'time_format' => $this->getSetting('general.time_format', 'H:i'),
                'items_per_page' => (int) $this->getSetting('general.items_per_page', 25),
                'maintenance_mode' => (bool) $this->getSetting('general.maintenance_mode', false),
                'allow_registration' => (bool) $this->getSetting('general.allow_registration', true),
                'allow_public_api' => (bool) $this->getSetting('general.allow_public_api', false),
            ];
        });
    }

    /**
     * Obtener configuraciones de moneda
     */
    protected function getCurrencySettings(): array
    {
        $baseCurrency = $this->currencyService->getBaseCurrency();
        $defaultCurrency = $this->currencyService->getDefaultCurrency();

        return [
            'base_currency' => $baseCurrency ? [
                'id' => $baseCurrency->id,
                'code' => $baseCurrency->code,
                'name' => $baseCurrency->name,
                'symbol' => $baseCurrency->symbol,
            ] : null,
            'default_currency' => $defaultCurrency ? [
                'id' => $defaultCurrency->id,
                'code' => $defaultCurrency->code,
                'name' => $defaultCurrency->name,
                'symbol' => $defaultCurrency->symbol,
            ] : null,
            'available_currencies' => Currency::active()
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
                }),
            'decimal_places' => (int) $this->getSetting('currency.decimal_places', 2),
            'currency_display' => $this->getSetting('currency.currency_display', 'symbol'),
        ];
    }

    /**
     * Obtener configuraciones de notificaciones
     */
    protected function getNotificationSettings(): array
    {
        return Cache::remember('settings_notification', 3600, function () {
            return [
                'email_enabled' => (bool) $this->getSetting('notification.email_enabled', true),
                'push_enabled' => (bool) $this->getSetting('notification.push_enabled', true),
                'in_app_enabled' => (bool) $this->getSetting('notification.in_app_enabled', true),
                'transaction_notifications' => (bool) $this->getSetting('notification.transaction_notifications', true),
                'report_notifications' => (bool) $this->getSetting('notification.report_notifications', false),
                'system_notifications' => (bool) $this->getSetting('notification.system_notifications', true),
                'email_digest_frequency' => $this->getSetting('notification.email_digest_frequency', 'daily'),
                'push_on_transaction' => (bool) $this->getSetting('notification.push_on_transaction', true),
                'daily_summary' => (bool) $this->getSetting('notification.daily_summary', false),
            ];
        });
    }

    /**
     * Obtener configuraciones de seguridad
     */
    protected function getSecuritySettings(): array
    {
        return Cache::remember('settings_security', 3600, function () {
            return [
                'session_timeout' => (int) $this->getSetting('security.session_timeout', 120),
                'max_login_attempts' => (int) $this->getSetting('security.max_login_attempts', 5),
                'lockout_duration' => (int) $this->getSetting('security.lockout_duration', 15),
                'password_min_length' => (int) $this->getSetting('security.password_min_length', 8),
                'password_requires_uppercase' => (bool) $this->getSetting('security.password_requires_uppercase', true),
                'password_requires_numbers' => (bool) $this->getSetting('security.password_requires_numbers', true),
                'password_requires_symbols' => (bool) $this->getSetting('security.password_requires_symbols', true),
                'two_factor_enabled' => (bool) $this->getSetting('security.two_factor_enabled', false),
                'session_per_user' => (bool) $this->getSetting('security.session_per_user', false),
            ];
        });
    }

    /**
     * Obtener configuraciones de empresa
     */
    protected function getCompanySettings(): array
    {
        $companyId = $this->getCurrentCompanyId();
        $company = Company::find($companyId);

        if (!$company) {
            return [];
        }

        return [
            'id' => $company->id,
            'name' => $company->name,
            'business_name' => $company->business_name,
            'tax_id' => $company->tax_id,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'logo_url' => $company->logo_url,
            'theme' => $company->theme ?? 'light',
            'timezone' => $company->timezone,
            'is_active' => $company->is_active,
        ];
    }

    /**
     * Obtener una configuración específica
     */
    protected function getSetting(string $key, $default = null)
    {
        $setting = DB::table('settings')
            ->where('key', $key)
            ->first();

        if ($setting) {
            return $setting->value;
        }

        return $default;
    }

    /**
     * Guardar una configuración
     */
    protected function saveSetting(string $key, $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : $value,
                'updated_at' => now(),
            ]
        );
    }
}
