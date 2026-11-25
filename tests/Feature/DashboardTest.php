<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\User;
use App\Services\MembershipBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_admin_access(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_reports_members_transactions_and_actual_payments(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $category = MembershipCategory::create([
            'name' => 'Dashboard test category',
            'fee' => '0.00',
            'is_active' => true,
        ]);

        $level = MembershipLevel::create([
            'name' => 'Dashboard test level',
            'fee' => '0.29',
            'validity_days' => 30,
            'is_active' => true,
        ]);

        $makeMember = function (string $email) use (
            $category,
            $level
        ): Member {
            return Member::create([
                'first_name' => 'Dashboard',
                'last_name' => 'Member',
                'email' => $email,
                'membership_category_id' => $category->id,
                'membership_level_id' => $level->id,
                ...$level->terms(),
                'status' => 'Inactive',
                'joined_at' => today('Asia/Manila')->toDateString(),
            ]);
        };

        $first = $makeMember('dashboard-first@example.test');
        $second = $makeMember('dashboard-second@example.test');

        $billing = app(MembershipBilling::class);

        $firstTransaction = $billing->createInitial(
            $first->id,
            $admin
        );

        $billing->recordPayment($firstTransaction->id, [
            'amount' => '0.10',
            'method' => 'cash',
            'external_reference' => null,
            'idempotency_key' => (string) Str::uuid(),
        ], $admin);

        $billing->recordPayment($firstTransaction->id, [
            'amount' => '0.19',
            'method' => 'cash',
            'external_reference' => null,
            'idempotency_key' => (string) Str::uuid(),
        ], $admin);

        $billing->approve($firstTransaction->id, $admin);
        $billing->createInitial($second->id, $admin);

        MembershipApplication::create([
            'submission_key' => (string) Str::uuid(),
            'membership_category_id' => $category->id,
            'full_name' => 'Pending Applicant',
            'email' => 'pending-dashboard@example.test',
            'privacy_consented_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('stats', function (array $stats): bool {
                return $stats['total_members'] === 2
                    && $stats['active_members'] === 1
                    && $stats['pending_transactions'] === 1
                    && $stats['awaiting_approval'] === 0
                    && $stats['pending_applications'] === 1
                    && $stats['collected'] === '0.29';
            })
            ->assertSee($firstTransaction->reference_no)
            ->assertSee('₱0.29');
    }
}