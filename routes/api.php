<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Middleware\EnsureCompanyContext;
use App\Http\Middleware\CheckCompanyAccess;
use App\Http\Middleware\LogUserActivity;
use Illuminate\Support\Facades\Route;

// ================================================================
// 1. RUTAS PÚBLICAS (Sin autenticación)
// ================================================================

Route::prefix('public')->group(function () {
    Route::get('/health', [HealthController::class, 'check']);
    Route::get('/version', [HealthController::class, 'version']);
    Route::get('/banks', [BankController::class, 'index']);
    Route::get('/currencies', [CurrencyController::class, 'index']);
});

// ================================================================
// 2. RUTAS DE AUTENTICACIÓN (Sin empresa)
// ================================================================

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/switch-company', [AuthController::class, 'switchCompany']);
        Route::get('/check', [AuthController::class, 'checkAuth']);
        Route::get('/companies', [AuthController::class, 'availableCompanies']);
    });
});

// ================================================================
// 3. RUTAS DE PERFIL (Sin empresa)
// ================================================================

Route::middleware(['auth:sanctum'])->prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::put('/', [ProfileController::class, 'update']);
    Route::post('/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::delete('/avatar', [ProfileController::class, 'deleteAvatar']);
    Route::post('/change-password', [ProfileController::class, 'changePassword']);
    Route::get('/activity', [ProfileController::class, 'activity']);
    Route::get('/sessions', [ProfileController::class, 'sessions']);
    Route::delete('/sessions/{id}', [ProfileController::class, 'revokeSession']);
    Route::delete('/sessions', [ProfileController::class, 'revokeAllSessions']);
});

// ================================================================
// 4. RUTAS CON CONTEXTO DE EMPRESA - USANDO CLASES DIRECTAMENTE
// ================================================================

Route::middleware([
    'auth:sanctum',
    EnsureCompanyContext::class,
    CheckCompanyAccess::class,
    LogUserActivity::class,
])->group(function () {

    // 4.1 Companies
    Route::prefix('companies')->group(function () {

        // ✅ PRIMERO: Rutas específicas (sin parámetros variables)
        Route::get('/me', [CompanyController::class, 'myCompany']);      // ← Antes de {id}
        Route::get('/current', [CompanyController::class, 'myCompany']); // ← También funciona

        // ✅ DESPUÉS: Rutas con parámetros
        Route::get('/', [CompanyController::class, 'index']);
        Route::post('/', [CompanyController::class, 'store']);
        Route::get('/{id}', [CompanyController::class, 'show']);        // ← Después de /me
        Route::put('/{id}', [CompanyController::class, 'update']);
        Route::delete('/{id}', [CompanyController::class, 'destroy']);
        Route::post('/{id}/logo', [CompanyController::class, 'uploadLogo']);
        Route::delete('/{id}/logo', [CompanyController::class, 'deleteLogo']);
        Route::get('/{id}/stats', [CompanyController::class, 'stats']);
        Route::post('/{id}/subscription', [CompanyController::class, 'changeSubscription']);
    });

    // 4.2 Banks
    Route::prefix('banks')->group(function () {
        Route::get('/', [BankController::class, 'index']);
        Route::get('/search', [BankController::class, 'search']);
        Route::get('/countries', [BankController::class, 'countries']);
        Route::get('/options', [BankController::class, 'options']);
        Route::get('/{id}', [BankController::class, 'show']);

        // Escritura solo para admin
        Route::middleware(['can:manage-banks'])->group(function () {
            Route::post('/', [BankController::class, 'store']);
            Route::put('/{id}', [BankController::class, 'update']);
            Route::delete('/{id}', [BankController::class, 'destroy']);
            Route::post('/{id}/toggle', [BankController::class, 'toggle']);
        });
    });

    // 4.3 Transactions
    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::post('/', [TransactionController::class, 'store']);
        Route::post('/transfer', [TransactionController::class, 'transfer']);
        Route::post('/reconvert', [TransactionController::class, 'reconvert']);
        Route::get('/stats', [TransactionController::class, 'stats']);
        Route::get('/summary', [TransactionController::class, 'summary']);
        Route::get('/{id}', [TransactionController::class, 'show']);
        Route::put('/{id}', [TransactionController::class, 'update']);
        Route::delete('/{id}', [TransactionController::class, 'destroy']);
    });

    // 4.4 Reports
    Route::prefix('reports')->group(function () {
        Route::get('/cash-flow', [ReportController::class, 'cashFlow']);
        Route::get('/executive', [ReportController::class, 'executiveSummary']);
        Route::get('/comparative', [ReportController::class, 'comparativeBalance']);
        Route::get('/categories', [ReportController::class, 'categoryReport']);
        Route::get('/transactions', [ReportController::class, 'transactionReport']);
        Route::get('/accounts', [ReportController::class, 'accountReport']);
        Route::get('/daily-summary', [ReportController::class, 'dailySummary']);
        Route::get('/monthly-comparison', [ReportController::class, 'monthlyComparison']);
        Route::get('/yearly-summary', [ReportController::class, 'yearlySummary']);
    });

    // 4.5 Categories
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/tree', [CategoryController::class, 'tree']);
        Route::get('/income', [CategoryController::class, 'getIncomeCategories']);
        Route::get('/expense', [CategoryController::class, 'getExpenseCategories']);
        Route::get('/with-colors', [CategoryController::class, 'withColors']);
        Route::get('/search', [CategoryController::class, 'search']);
        Route::get('/{id}', [CategoryController::class, 'show']);
        Route::get('/{id}/descendants', [CategoryController::class, 'descendants']);

        // Escritura (requiere admin/super_admin)
        Route::post('/', [CategoryController::class, 'store']);
        Route::post('/{id}/move', [CategoryController::class, 'move']);
        Route::post('/{id}/toggle', [CategoryController::class, 'toggle']);
        Route::put('/{id}', [CategoryController::class, 'update']);
        Route::delete('/{id}', [CategoryController::class, 'destroy']);
    });

    // 4.6 Accounts
    Route::prefix('accounts')->group(function () {
        Route::get('/', [AccountController::class, 'index']);
        Route::get('/income', [AccountController::class, 'getIncomeAccounts']);
        Route::get('/expense', [AccountController::class, 'getExpenseAccounts']);
        Route::get('/statistics', [AccountController::class, 'statistics']);
        Route::get('/search', [AccountController::class, 'search']);
        Route::get('/{id}', [AccountController::class, 'show']);
        Route::get('/category/{categoryId}', [AccountController::class, 'getByCategory']);

        Route::middleware(['can:manage-accounts'])->group(function () {
            Route::post('/', [AccountController::class, 'store']);
            Route::post('/{id}/toggle', [AccountController::class, 'toggle']);
            Route::put('/{id}', [AccountController::class, 'update']);
            Route::delete('/{id}', [AccountController::class, 'destroy']);
        });
    });

    // 4.7 Bank Accounts
    Route::prefix('bank-accounts')->group(function () {
        Route::get('/', [BankAccountController::class, 'index']);
        Route::get('/summary', [BankAccountController::class, 'summary']);
        Route::get('/{id}', [BankAccountController::class, 'show']);
        Route::get('/{id}/balance', [BankAccountController::class, 'balance']);
        Route::get('/{id}/balance-history', [BankAccountController::class, 'balanceHistory']);
        Route::post('/', [BankAccountController::class, 'store']);
        Route::put('/{id}', [BankAccountController::class, 'update']);
        Route::delete('/{id}', [BankAccountController::class, 'destroy']);

        // ✅ NUEVAS RUTAS
        Route::post('/{id}/toggle', [BankAccountController::class, 'toggle']);
        Route::post('/{id}/set-default', [BankAccountController::class, 'setDefault']);
    });

    // 4.8 Currencies
    Route::prefix('currencies')->group(function () {
        Route::get('/', [CurrencyController::class, 'index']);
        Route::get('/options', [CurrencyController::class, 'options']);
        Route::get('/base', [CurrencyController::class, 'getBase']);
        Route::get('/default', [CurrencyController::class, 'getDefault']);
        Route::get('/{id}', [CurrencyController::class, 'show']);

        Route::middleware(['can:manage-currencies'])->group(function () {
            Route::post('/', [CurrencyController::class, 'store']);
            Route::post('/{id}/toggle', [CurrencyController::class, 'toggle']);
            Route::put('/{id}', [CurrencyController::class, 'update']);
            Route::delete('/{id}', [CurrencyController::class, 'destroy']);
        });
    });

    // 4.9 Exchange Rates
    Route::prefix('exchange-rates')->group(function () {
        Route::get('/', [ExchangeRateController::class, 'index']);
        Route::get('/latest', [ExchangeRateController::class, 'latest']);
        Route::get('/historical', [ExchangeRateController::class, 'historical']);
        Route::get('/convert', [ExchangeRateController::class, 'convert']);
        Route::get('/currencies', [ExchangeRateController::class, 'currencies']);
        Route::get('/{id}', [ExchangeRateController::class, 'show']);
        Route::post('/', [ExchangeRateController::class, 'store']);
        Route::put('/{id}', [ExchangeRateController::class, 'update']);
        Route::delete('/{id}', [ExchangeRateController::class, 'destroy']);
    });

    // 4.10 Users
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/company', [UserController::class, 'getByCompany']);
        Route::get('/stats', [UserController::class, 'stats']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::post('/{id}/toggle', [UserController::class, 'toggle']);
        Route::post('/{id}/assign-role', [UserController::class, 'assignRole']);
        Route::post('/{id}/remove-role', [UserController::class, 'removeRole']);
        Route::post('/{id}/assign-company', [UserController::class, 'assignCompany']);
        Route::post('/{id}/remove-company', [UserController::class, 'removeCompany']);
    });

    // 4.11 Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/trends', [DashboardController::class, 'trends']);
        Route::get('/recent', [DashboardController::class, 'recent']);
        Route::get('/category-distribution', [DashboardController::class, 'categoryDistribution']);
        Route::get('/quick-stats', [DashboardController::class, 'quickStats']);
        Route::get('/summary', [DashboardController::class, 'summary']);
    });

    // 4.12 Settings
    Route::prefix('settings')->group(function () {
        // Todas las configuraciones
        Route::get('/', [SettingsController::class, 'index']);

        // Configuraciones generales
        Route::get('/general', [SettingsController::class, 'getGeneral']);
        Route::put('/general', [SettingsController::class, 'updateGeneral']);

        // Configuraciones de moneda
        Route::get('/currency', [SettingsController::class, 'getCurrency']);
        Route::put('/currency', [SettingsController::class, 'updateCurrency']);

        // Configuraciones de notificaciones
        Route::get('/notification', [SettingsController::class, 'getNotification']);
        Route::put('/notification', [SettingsController::class, 'updateNotification']);

        // Configuraciones de seguridad
        Route::get('/security', [SettingsController::class, 'getSecurity']);
        Route::put('/security', [SettingsController::class, 'updateSecurity']);

        // Configuraciones de empresa
        Route::get('/company', [SettingsController::class, 'getCompany']);
        Route::put('/company', [SettingsController::class, 'updateCompany']);

        // Preferencias de usuario
        Route::get('/preferences', [SettingsController::class, 'getPreferences']);
        Route::put('/preferences', [SettingsController::class, 'updatePreferences']);

        // Limpiar caché
        Route::post('/cache/clear', [SettingsController::class, 'clearCache']);
    });
});

// ================================================================
// 5. RUTAS DE AUDITORÍA (Con autenticación)
// ================================================================

Route::middleware(['auth:sanctum'])->prefix('audit-logs')->group(function () {
    Route::get('/', [AuditLogController::class, 'index']);
    Route::get('/stats', [AuditLogController::class, 'stats']);
    Route::get('/{id}', [AuditLogController::class, 'show']);
    Route::get('/entity/{type}/{id}', [AuditLogController::class, 'byEntity']);
    Route::get('/user/{id}', [AuditLogController::class, 'byUser']);
});
