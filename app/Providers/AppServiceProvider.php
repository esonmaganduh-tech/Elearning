<?php

namespace App\Providers;

use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Observers\DomainAuditObserver;
use App\Observers\MembershipTransactionObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define(
            'access-admin',
            fn (User $user): bool => (bool) $user->is_admin
        );

        MembershipTransaction::observe(
            MembershipTransactionObserver::class
        );

        foreach ([
            Member::class,
            MembershipCategory::class,
            MembershipLevel::class,
            MembershipApplication::class,
            MembershipTransaction::class,
            Payment::class,
        ] as $modelClass) {
            $modelClass::observe(DomainAuditObserver::class);
        }
    }
}