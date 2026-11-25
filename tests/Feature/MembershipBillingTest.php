<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Services\MembershipBilling;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MembershipBillingTest extends TestCase
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

        $this->actor = User::factory()->create([
            'is_admin' => true,
        ]);

        $category = MembershipCategory::where('name', 'Student')
            ->firstOrFail();

        $category->update(['fee' => '100.00']);

        $level = MembershipLevel::where('name', 'Basic')
            ->firstOrFail();

        $level->update([
            'fee' => '499.00',
            'validity_days' => 30,
            'benefits' => 'Included benefits',
        ]);

        $this->member = Member::create([
            'first_name' => 'Billing',
            'last_name' => 'Test',
            'email' => 'billing@example.com',
            'membership_category_id' => $category->id,
            'membership_level_id' => $level->id,
            'fee' => '777.25',
            'validity_period' => 'Legacy validity',
            'benefits' => 'Legacy benefits',
            'status' => 'Active',
            'joined_at' => '2026-11-25',
        ]);
    }

    private function transaction(): MembershipTransaction
    {
        return $this->billing->createInitial(
            $this->member->id,
            $this->actor
        );
    }

    private function pay(
        MembershipTransaction $transaction,
        string $amount,
        ?string $key = null
    ): Payment {
        return $this->billing->recordPayment(
            $transaction->id,
            [
                'amount' => $amount,
                'method' => 'cash',
                'idempotency_key' => $key ?? (string) Str::uuid(),
            ],
            $this->actor
        );
    }

    private function assertInvalid(
        Closure $action,
        string $field
    ): void {
        try {
            $action();

            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                $field,
                $exception->errors()
            );
        }
    }

    public function test_initial_quote_is_created_once(): void
    {
        $first = $this->transaction();
        $second = $this->transaction();

        $this->assertSame($first->id, $second->id);
        $this->assertStringStartsWith('TXN-', $first->reference_no);
        $this->assertSame('599.00', $first->total_amount);
        $this->assertSame('pending', $first->status);
        $this->assertDatabaseCount('membership_transactions', 1);

        $member = $this->member->fresh();

        $this->assertSame('Inactive', $member->status);
        $this->assertSame('777.25', $member->fee);
    }

    public function test_partial_payment_then_full_payment_and_approval(): void
    {
        $transaction = $this->transaction();

        $this->pay($transaction, '100.00');

        $this->assertSame(
            'pending',
            $transaction->fresh()->status
        );

        $this->assertInvalid(
            fn () => $this->billing->approve(
                $transaction->id,
                $this->actor
            ),
            'transaction'
        );

        $this->pay($transaction, '499.00');

        $this->assertSame('paid', $transaction->fresh()->status);

        $approved = $this->billing->approve(
            $transaction->id,
            $this->actor
        );

        $this->assertSame('approved', $approved->status);
        $this->assertSame(
            '2026-11-25',
            $approved->starts_on->toDateString()
        );
        $this->assertSame(
            '2026-12-24',
            $approved->expires_on->toDateString()
        );

        $member = $this->member->fresh();

        $this->assertSame('Active', $member->status);
        $this->assertSame('499.00', $member->fee);
        $this->assertSame('Included benefits', $member->benefits);

        $this->travel(1)->days();

        $again = $this->billing->approve(
            $transaction->id,
            $this->actor
        );

        $this->assertSame(
            '2026-12-24',
            $again->expires_on->toDateString()
        );

        $this->assertInvalid(
            fn () => $this->transaction(),
            'transaction'
        );
    }

    public function test_invalid_and_excess_payments_are_rejected(): void
    {
        $transaction = $this->transaction();

        foreach (['0', '-1', '1.001', '600.00'] as $amount) {
            $this->assertInvalid(
                fn () => $this->pay($transaction, $amount),
                'amount'
            );
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('pending', $transaction->fresh()->status);
    }

    public function test_payment_retries_do_not_create_duplicates(): void
    {
        $transaction = $this->transaction();
        $key = (string) Str::uuid();

        $first = $this->pay($transaction, '599.00', $key);

        $this->billing->approve(
            $transaction->id,
            $this->actor
        );

        $retry = $this->pay(
            $transaction,
            '599.00',
            strtoupper($key)
        );

        $this->assertSame($first->id, $retry->id);
        $this->assertDatabaseCount('payments', 1);

        $this->assertInvalid(
            fn () => $this->pay($transaction, '598.00', $key),
            'idempotency_key'
        );
    }

    public function test_catalog_changes_do_not_change_an_issued_quote(): void
    {
        $transaction = $this->transaction();

        $this->member->membershipLevel->update([
            'name' => 'Updated Basic',
            'fee' => '9999.00',
            'validity_days' => 365,
            'benefits' => 'Changed benefits',
        ]);

        $this->pay($transaction, '599.00');

        $this->billing->approve(
            $transaction->id,
            $this->actor
        );

        $transaction->refresh();
        $member = $this->member->fresh();

        $this->assertSame('Basic', $transaction->level_name);
        $this->assertSame('599.00', $transaction->total_amount);
        $this->assertSame('499.00', $member->fee);
        $this->assertSame('30 days', $member->validity_period);
        $this->assertSame('Included benefits', $member->benefits);
    }

    public function test_unpaid_transaction_can_be_cancelled(): void
    {
        $transaction = $this->transaction();

        $cancelled = $this->billing->cancel(
            $transaction->id,
            $this->actor
        );

        $this->assertSame('cancelled', $cancelled->status);

        $this->assertInvalid(
            fn () => $this->pay($transaction, '100.00'),
            'transaction'
        );

        $this->assertInvalid(
            fn () => $this->billing->approve(
                $transaction->id,
                $this->actor
            ),
            'transaction'
        );

        $replacement = $this->transaction();

        $this->assertNotSame(
            $transaction->id,
            $replacement->id
        );
    }

    public function test_transaction_with_payments_cannot_be_cancelled(): void
    {
        $transaction = $this->transaction();

        $this->pay($transaction, '100.00');

        $this->assertInvalid(
            fn () => $this->billing->cancel(
                $transaction->id,
                $this->actor
            ),
            'transaction'
        );

        $this->assertSame('pending', $transaction->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_external_payment_reference_cannot_be_reused(): void
    {
        $firstTransaction = $this->transaction();

        $this->billing->recordPayment(
            $firstTransaction->id,
            [
                'amount' => '100.00',
                'method' => 'bank_transfer',
                'external_reference' => 'BANK-001',
                'idempotency_key' => (string) Str::uuid(),
            ],
            $this->actor
        );

        $otherMember = $this->member->replicate();
        $otherMember->email = 'other@example.com';
        $otherMember->save();

        $otherTransaction = $this->billing->createInitial(
            $otherMember->id,
            $this->actor
        );

        $this->assertInvalid(
            fn () => $this->billing->recordPayment(
                $otherTransaction->id,
                [
                    'amount' => '100.00',
                    'method' => 'bank_transfer',
                    'external_reference' => ' bank-001 ',
                    'idempotency_key' => (string) Str::uuid(),
                ],
                $this->actor
            ),
            'external_reference'
        );

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_centavo_calculations_are_exact(): void
    {
        $this->member->membershipCategory->update([
            'fee' => '0.10',
        ]);

        $this->member->membershipLevel->update([
            'fee' => '0.20',
        ]);

        $transaction = $this->transaction();

        $this->assertSame('0.30', $transaction->total_amount);

        $this->pay($transaction, '0.10');
        $this->pay($transaction, '0.20');

        $transaction->refresh();

        $this->assertSame('paid', $transaction->status);
        $this->assertSame(30, $transaction->paidCents());
        $this->assertSame(0, $transaction->balanceCents());
    }

    public function test_free_membership_needs_no_payment_record(): void
    {
        $this->member->membershipCategory->update(['fee' => 0]);
        $this->member->membershipLevel->update(['fee' => 0]);

        $transaction = $this->transaction();

        $this->assertSame('paid', $transaction->status);

        $this->billing->approve(
            $transaction->id,
            $this->actor
        );

        $this->assertSame(
            'Active',
            $this->member->fresh()->status
        );

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_paid_label_without_payments_does_not_allow_approval(): void
    {
        $transaction = $this->transaction();

        $transaction->update(['status' => 'paid']);

        $this->assertInvalid(
            fn () => $this->billing->approve(
                $transaction->id,
                $this->actor
            ),
            'transaction'
        );

        $this->assertSame(
            'Inactive',
            $this->member->fresh()->status
        );
    }

    public function test_member_with_transaction_history_cannot_be_deleted(): void
    {
        $this->transaction();

        $this->expectException(QueryException::class);

        $this->member->delete();
    }

    public function test_ordinary_account_cannot_process_transactions(): void
    {
        $ordinaryUser = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        $this->billing->createInitial(
            $this->member->id,
            $ordinaryUser
        );
    }
}