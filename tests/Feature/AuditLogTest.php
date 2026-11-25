<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\User;
use App\Services\MembershipBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create([
            'name' => 'Audit Admin',
        ]);

        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function fixtures(): array
    {
        $category = MembershipCategory::create([
            'name' => 'Audit test category',
            'fee' => '0.00',
            'is_active' => true,
        ]);

        $level = MembershipLevel::create([
            'name' => 'Audit test level',
            'fee' => '0.29',
            'validity_days' => 30,
            'is_active' => true,
        ]);

        $member = Member::create([
            'first_name' => 'Alex',
            'last_name' => 'Santos',
            'email' => 'private-old@example.test',
            'phone' => '09170000000',
            'membership_category_id' => $category->id,
            'membership_level_id' => $level->id,
            ...$level->terms(),
            'status' => 'Inactive',
            'joined_at' => today('Asia/Manila')->toDateString(),
        ]);

        return [$category, $level, $member];
    }

    public function test_audit_screen_requires_admin_access(): void
    {
        $this->get(route('audit-logs.index'))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('audit-logs.index'))
            ->assertForbidden();
    }

    public function test_contact_changes_record_actor_and_fields_without_values(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin);

        [, , $member] = $this->fixtures();

        $member->update([
            'email' => 'private-new@example.test',
            'phone' => '09998887777',
            'notes' => 'Private customer note',
        ]);

        $log = AuditLog::query()
            ->where('subject_type', 'Member')
            ->where('subject_id', $member->id)
            ->where('action', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($admin->id, $log->actor_id);
        $this->assertContains('email', $log->changed_fields);
        $this->assertContains('phone', $log->changed_fields);
        $this->assertContains('notes', $log->changed_fields);

        $json = $log->toJson();

        $this->assertStringNotContainsString(
            'private-old@example.test',
            $json
        );

        $this->assertStringNotContainsString(
            'private-new@example.test',
            $json
        );

        $this->assertStringNotContainsString('09998887777', $json);
        $this->assertStringNotContainsString('Private customer note', $json);

        $this->get(
            route('audit-logs.index', ['q' => $member->membership_no])
        )
            ->assertOk()
            ->assertSee($member->membership_no)
            ->assertSee('Audit Admin');
    }

    public function test_outer_rollback_removes_business_change_and_audit_entry(): void
    {
        $before = AuditLog::count();

        DB::beginTransaction();

        try {
            MembershipCategory::create([
                'name' => 'Rolled back category',
                'fee' => '10.00',
                'is_active' => true,
            ]);

            $this->assertGreaterThan($before, AuditLog::count());

            DB::rollBack();
        } finally {
            while (DB::transactionLevel() > 1) {
                DB::rollBack();
            }
        }

        $this->assertDatabaseMissing('membership_categories', [
            'name' => 'Rolled back category',
        ]);

        $this->assertSame($before, AuditLog::count());
    }

    public function test_business_write_rolls_back_when_audit_insert_fails(): void
    {
        [, , $member] = $this->fixtures();

        $event = 'eloquent.creating: '.AuditLog::class;

        Event::listen($event, function (): void {
            throw new RuntimeException('Simulated audit failure');
        });

        try {
            $member->update(['phone' => '09991112222']);

            $this->fail('The audit failure should stop the write.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulated audit failure',
                $exception->getMessage()
            );
        } finally {
            Event::forget($event);
        }

        $this->assertSame(
            '09170000000',
            $member->fresh()->phone
        );
    }

    public function test_expiry_is_audited_while_renewed_member_stays_active(): void
    {
        $this->travelTo(
            \Illuminate\Support\Carbon::parse(
                '2026-11-25 10:00:00',
                'Asia/Manila'
            )
        );

        try {
            $admin = $this->admin();
            [$category, $level, $member] = $this->fixtures();

            $billing = app(MembershipBilling::class);

            $initial = $billing->createInitial($member->id, $admin);

            $billing->recordPayment($initial->id, [
                'amount' => '0.29',
                'method' => 'cash',
                'external_reference' => null,
                'idempotency_key' => (string) Str::uuid(),
            ], $admin);

            $billing->approve($initial->id, $admin);

            $renewal = $billing->createRenewal(
                $member->id,
                $category->id,
                $level->id,
                $admin
            );

            $billing->recordPayment($renewal->id, [
                'amount' => '0.29',
                'method' => 'cash',
                'external_reference' => null,
                'idempotency_key' => (string) Str::uuid(),
            ], $admin);

            $billing->approve($renewal->id, $admin);

            $this->travel(31)->days();

            $billing->expireMemberships();

            $this->assertSame('expired', $initial->fresh()->status);
            $this->assertSame('approved', $renewal->fresh()->status);
            $this->assertSame('Active', $member->fresh()->status);

            $log = AuditLog::query()
                ->where('subject_type', 'MembershipTransaction')
                ->where('subject_id', $initial->id)
                ->where('action', 'updated')
                ->latest('id')
                ->firstOrFail();

            $this->assertSame(
                'approved',
                $log->old_values['status']
            );

            $this->assertSame(
                'expired',
                $log->new_values['status']
            );

            $this->assertSame('system', $log->actor_kind);
        } finally {
            $this->travelBack();
        }
    }

    public function test_audit_entry_cannot_be_deleted_through_model(): void
    {
        $this->fixtures();

        $log = AuditLog::firstOrFail();

        $this->expectException(LogicException::class);

        $log->delete();
    }
}