<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipTransaction;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('access-admin');

        $today = today('Asia/Manila')->toDateString();

        $activeMembers = Member::query()
            ->where('status', 'Active')
            ->where(function ($query) use ($today): void {
                $query->whereNull('membership_expires_on')
                    ->orWhereDate(
                        'membership_expires_on',
                        '>=',
                        $today
                    );
            })
            ->count();

        $collectedCents = (int) Payment::query()
            ->selectRaw(
                'COALESCE(SUM(ROUND(amount * 100, 0)), 0) AS total_cents'
            )
            ->value('total_cents');

        $stats = [
            'total_members' => Member::count(),
            'active_members' => $activeMembers,

            'pending_transactions' => MembershipTransaction::query()
                ->where('status', 'pending')
                ->count(),

            'awaiting_approval' => MembershipTransaction::query()
                ->where('status', 'paid')
                ->count(),

            'pending_applications' => MembershipApplication::query()
                ->where('status', 'pending')
                ->count(),

            'collected' => Money::decimal($collectedCents),
        ];

        $recentTransactions = MembershipTransaction::query()
            ->latest('id')
            ->limit(10)
            ->get();

        return view('dashboard', compact(
            'stats',
            'recentTransactions'
        ));
    }
}