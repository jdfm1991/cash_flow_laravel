<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Company;
use App\Models\Transaction;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Account;
use App\Models\User;
use App\Models\Bank;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Role;
use App\Policies\CompanyPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\BankAccountPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\AccountPolicy;
use App\Policies\UserPolicy;
use App\Policies\BankPolicy;
use App\Policies\CurrencyPolicy;
use App\Policies\ExchangeRatePolicy;
use App\Policies\RolePolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Company::class => CompanyPolicy::class,
        Transaction::class => TransactionPolicy::class,
        BankAccount::class => BankAccountPolicy::class,
        Category::class => CategoryPolicy::class,
        Account::class => AccountPolicy::class,
        User::class => UserPolicy::class,
        Bank::class => BankPolicy::class,
        Currency::class => CurrencyPolicy::class,
        ExchangeRate::class => ExchangeRatePolicy::class,
        Role::class => RolePolicy::class
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // ✅ Definir un Gate para super_admin
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
        });
    }
}
