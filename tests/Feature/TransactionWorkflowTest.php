<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\User;
use App\Services\MembershipBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private MembershipBilling $billing;
    private User $actor;
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(
            Carbon::parse('2026-11-25 10:00:00', 'UTC')
        );

        $this->billing = app(MembershipBilling::class);
        $this->actor = User::factory()->create(['is_admin' => true]);
        $this->actingAs($this->actor);

        $category = MembershipCategory::where('name', 'Student')
            ->firstOrFail();

        $level = MembershipLevel::where('name', 'Basic')
            ->firstOrFail();

        $this->member = Member::create([
            'first_name' => 'Workflow',
            'last_name' => 'Member',
            'email' => 'workflow@example.com',
            'membership_category_id' => $category->id,
            'membership_level_id' => $level->id,
            'fee' => '499.00',
            'validity_period' => '30 days',
            'status' => 'Inactive',
            'joined_at' => '2026-11-25',
        ]);
    }

    private function paymentData(
        string $amount,
        ?string $key = null
    ): array {
        return [
            'amount' => $amount,
            'method' => 'cash',
            'idempotency_key' => $key ?? (string) Str::uuid(),
        ];
    }

    private function approvedInitial(): MembershipTransaction
    {
        $transaction = $this->billing->createInitial(
            $this->member->id,
            $this->actor
        );

        $this->billing->recordPayment(
            $transaction->id,
            $this->paymentData($transaction->total_amount),
            $this->actor
        );

        return $this->billing->approve(
            $transaction->id,
            $this->actor
        );
    }

    private function memberInput(): array
    {
        return [
            'first_name' => 'Updated',
            'last_name' => $this->member->last_name,
            'email' => $this->member->email,
            'membership_category_id' => $this->member->membership_category_id,
            'membership_level_id' => $this->member->membership_level_id,
            'joined_at' => '2026-11-25',
        ];
    }

    public function test_transaction_screens_and_payment_retry_work(): void
    {
        $this->get("/members/{$this->member->id}/transactions/create")
            ->assertOk();

        $this->post("/members/{$this->member->id}/transactions", [
            'type' => 'membership',
        ])->assertSessionHasNoErrors();

        $transaction = MembershipTransaction::sole();

        $this->get('/transactions')->assertOk();

        $this->get("/transactions/{$transaction->id}")
            ->assertOk()
            ->assertSee($transaction->reference_no);

        $data = $this->paymentData('100.00');

        $this->post("/transactions/{$transaction->id}/payments", $data)
            ->assertSessionHasNoErrors();

        $this->post("/transactions/{$transaction->id}/payments", $data)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('payments', 1);

        $this->get("/transactions/{$transaction->id}/receipt")
            ->assertOk()
            ->assertSee('Partially paid')
            ->assertSee('100.00');

        $this->from("/transactions/{$transaction->id}")
            ->post("/transactions/{$transaction->id}/approve")
            ->assertSessionHasErrors('transaction');
    }

    public function test_member_plan_and_status_cannot_bypass_billing(): void
    {
        $this->billing->createInitial(
            $this->member->id,
            $this->actor
        );

        $otherLevel = MembershipLevel::where('name', 'Silver')
            ->firstOrFail();

        $this->put("/members/{$this->member->id}", array_replace(
            $this->memberInput(),
            ['membership_level_id' => $otherLevel->id]
        ))->assertSessionHasErrors('membership_level_id');

        $this->put("/members/{$this->member->id}", array_replace(
            $this->memberInput(),
            ['status' => 'Active']
        ))->assertSessionHasNoErrors();

        $this->assertSame('Inactive', $this->member->fresh()->status);

        $this->from("/members/{$this->member->id}")
            ->delete("/members/{$this->member->id}")
            ->assertSessionHasErrors('member');

        $this->assertModelExists($this->member);
    }

    public function test_early_renewal_preserves_remaining_days(): void
    {
        $initial = $this->approvedInitial();

        $this->post("/members/{$this->member->id}/transactions", [
            'type' => 'renewal',
            'membership_category_id' => $this->member->membership_category_id,
            'membership_level_id' => $this->member->membership_level_id,
        ])->assertSessionHasNoErrors();

        $renewal = MembershipTransaction::where('type', 'renewal')->sole();

        $this->assertSame('0.00', $renewal->category_fee);

        $this->post(
            "/transactions/{$renewal->id}/payments",
            $this->paymentData($renewal->total_amount)
        )->assertSessionHasNoErrors();

        $this->post("/transactions/{$renewal->id}/approve")
            ->assertSessionHasNoErrors();

        $renewal->refresh();

        $this->assertSame('2026-12-25', $renewal->starts_on->toDateString());
        $this->assertSame('2027-01-23', $renewal->expires_on->toDateString());

        $member = $this->member->fresh();

        $this->assertSame('2026-11-25', $member->membership_started_on->toDateString());
        $this->assertSame('2027-01-23', $member->membership_expires_on->toDateString());

        $this->travel(31)->days();

        $this->artisan('memberships:expire')->assertSuccessful();

        $this->assertSame('expired', $initial->fresh()->status);
        $this->assertSame('approved', $renewal->fresh()->status);
        $this->assertSame('Active', $this->member->fresh()->status);

        $this->travel(30)->days();

        $this->artisan('memberships:expire')->assertSuccessful();

        $this->assertSame('expired', $renewal->fresh()->status);
        $this->assertSame('Inactive', $this->member->fresh()->status);
    }

    public function test_unexpired_membership_cannot_switch_plans(): void
    {
        $this->approvedInitial();

        $silver = MembershipLevel::where('name', 'Silver')->firstOrFail();

        $this->post("/members/{$this->member->id}/transactions", [
            'type' => 'renewal',
            'membership_category_id' => $this->member->membership_category_id,
            'membership_level_id' => $silver->id,
        ])->assertSessionHasErrors('transaction');

        $this->assertDatabaseCount('membership_transactions', 1);
    }

    public function test_late_renewal_can_select_another_plan(): void
    {
        $this->approvedInitial();
        $this->travel(31)->days();

        $category = MembershipCategory::where('name', 'Regular')
            ->firstOrFail();

        $category->update(['fee' => '100.00']);

        $level = MembershipLevel::where('name', 'Silver')->firstOrFail();

        $renewal = $this->billing->createRenewal(
            $this->member->id,
            $category->id,
            $level->id,
            $this->actor
        );

        $this->assertSame('999.00', $renewal->total_amount);

        $this->billing->recordPayment(
            $renewal->id,
            $this->paymentData('999.00'),
            $this->actor
        );

        $this->billing->approve($renewal->id, $this->actor);

        $member = $this->member->fresh();

        $this->assertSame($category->id, $member->membership_category_id);
        $this->assertSame($level->id, $member->membership_level_id);
        $this->assertSame('2026-12-26', $member->membership_started_on->toDateString());
        $this->assertSame('2027-03-25', $member->membership_expires_on->toDateString());
    }

    public function test_transaction_routes_require_administrator_access(): void
    {
        $transaction = $this->billing->createInitial(
            $this->member->id,
            $this->actor
        );

        Auth::logout();

        $this->get('/transactions')->assertRedirect('/login');

        $this->post("/transactions/{$transaction->id}/approve")
            ->assertRedirect('/login');

        $this->actingAs(User::factory()->create());

        $this->get("/transactions/{$transaction->id}")
            ->assertForbidden();

        $this->get("/transactions/{$transaction->id}/receipt")
            ->assertForbidden();

        $this->post(
            "/transactions/{$transaction->id}/payments",
            $this->paymentData('100.00')
        )->assertForbidden();

        $this->post("/transactions/{$transaction->id}/approve")
            ->assertForbidden();

        $this->post("/transactions/{$transaction->id}/cancel")
            ->assertForbidden();
    }
}