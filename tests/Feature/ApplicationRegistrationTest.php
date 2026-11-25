<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApplicationRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function fixtures(): array
    {
        $category = MembershipCategory::create([
            'name' => 'Registration test category',
            'fee' => '0.00',
            'is_active' => true,
        ]);

        $level = MembershipLevel::create([
            'name' => 'Registration test level',
            'fee' => '0.29',
            'validity_days' => 30,
            'benefits' => 'Test benefits',
            'is_active' => true,
        ]);

        $application = MembershipApplication::create([
            'submission_key' => (string) Str::uuid(),
            'membership_category_id' => $category->id,
            'full_name' => 'Alex Santos',
            'email' => 'alex-registration@example.test',
            'phone' => '09171234567',
            'address' => 'Manila',
            'latitude' => '14.5995000',
            'longitude' => '120.9842000',
            'privacy_consented_at' => now(),
            'verification_sent_at' => now(),
        ]);

        return [$category, $level, $application];
    }

    private function registrationData(
        MembershipApplication $application,
        MembershipLevel $level
    ): array {
        return [
            'first_name' => 'Alex',
            'last_name' => 'Santos',
            'email' => $application->email,
            'membership_level_id' => $level->id,
        ];
    }

    public function test_registration_and_payment_approval_complete_application(): void
    {
        $admin = $this->admin();
        [, $level, $application] = $this->fixtures();

        $this->actingAs($admin)
            ->post(
                route('applications.register', $application),
                $this->registrationData($application, $level)
            )
            ->assertRedirect();

        $application->refresh();
        $member = $application->member()->firstOrFail();
        $transaction = $member->transactions()->sole();

        $this->assertSame('registered', $application->status);
        $this->assertSame($admin->id, $application->registered_by);
        $this->assertNotNull($application->registered_at);
        $this->assertSame('Inactive', $member->status);
        $this->assertSame('pending', $transaction->status);
        $this->assertSame('0.29', $transaction->total_amount);

        $this->post(route('payments.store', $transaction), [
            'amount' => '0.10',
            'method' => 'cash',
            'external_reference' => null,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->post(route('transactions.approve', $transaction))
            ->assertSessionHasErrors();

        $this->assertSame(
            'registered',
            $application->fresh()->status
        );

        $this->assertSame('Inactive', $member->fresh()->status);

        // Consume the validation message before continuing.
        $this->get(route('transactions.show', $transaction))
            ->assertOk();

        $this->post(route('payments.store', $transaction), [
            'amount' => '0.19',
            'method' => 'cash',
            'external_reference' => null,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $this->assertSame('paid', $transaction->fresh()->status);

        $this->post(route('transactions.approve', $transaction))
            ->assertRedirect();

        $this->assertSame(
            'approved',
            $application->fresh()->status
        );

        $this->assertSame('Active', $member->fresh()->status);
        $this->assertSame('approved', $transaction->fresh()->status);
        $this->assertNotNull($member->fresh()->membership_expires_on);
    }

    public function test_repeated_registration_reuses_member_and_transaction(): void
    {
        $admin = $this->admin();
        [, $level, $application] = $this->fixtures();

        $data = $this->registrationData($application, $level);

        $this->actingAs($admin)
            ->post(route('applications.register', $application), $data)
            ->assertRedirect();

        $memberId = $application->fresh()->member_id;

        $this->post(
            route('applications.register', $application),
            $data
        )->assertRedirect();

        $this->assertSame(
            $memberId,
            $application->fresh()->member_id
        );

        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseCount('membership_transactions', 1);
    }

    public function test_pending_application_can_be_cancelled(): void
    {
        $admin = $this->admin();
        [, , $application] = $this->fixtures();

        $this->actingAs($admin)
            ->post(route('applications.cancel', $application))
            ->assertRedirect(route('applications.show', $application));

        $this->assertSame(
            'cancelled',
            $application->fresh()->status
        );

        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('membership_transactions', 0);
    }

    public function test_registered_application_cannot_be_cancelled_here(): void
    {
        $admin = $this->admin();
        [, $level, $application] = $this->fixtures();

        $this->actingAs($admin)
            ->post(
                route('applications.register', $application),
                $this->registrationData($application, $level)
            );

        $this->post(route('applications.cancel', $application))
            ->assertSessionHasErrors('application');

        $this->assertSame(
            'registered',
            $application->fresh()->status
        );
    }

    public function test_inactive_level_does_not_create_partial_registration(): void
    {
        $admin = $this->admin();
        [, $level, $application] = $this->fixtures();

        $level->update(['is_active' => false]);

        $this->actingAs($admin)
            ->post(
                route('applications.register', $application),
                $this->registrationData($application, $level)
            )
            ->assertSessionHasErrors('membership_level_id');

        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseCount('membership_transactions', 0);
        $this->assertSame('pending', $application->fresh()->status);
        $this->assertNull($application->fresh()->member_id);
    }

    public function test_non_admin_cannot_register_or_cancel_applications(): void
    {
        [, $level, $application] = $this->fixtures();

        $this->actingAs(User::factory()->create())
            ->post(
                route('applications.register', $application),
                $this->registrationData($application, $level)
            )
            ->assertForbidden();

        $this->post(route('applications.cancel', $application))
            ->assertForbidden();

        $this->assertDatabaseCount('members', 0);
        $this->assertSame('pending', $application->fresh()->status);
    }
}