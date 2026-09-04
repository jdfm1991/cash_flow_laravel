<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Models\Transaction;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\Company;
use App\Models\ImportSession;
use App\Observers\TransactionObserver;
use App\Observers\BankAccountObserver;
use App\Observers\UserObserver;
use App\Observers\CompanyObserver;
use App\Observers\ImportSessionObserver;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // ... eventos existentes
    ];

    public function boot(): void
    {
        Transaction::observe(TransactionObserver::class);
        BankAccount::observe(BankAccountObserver::class);
        User::observe(UserObserver::class);
        Company::observe(CompanyObserver::class);
        ImportSession::observe(ImportSessionObserver::class);
    }
}