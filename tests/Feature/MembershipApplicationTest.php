<?php

namespace Tests\Feature;

use App\Models\MembershipApplication;
use App\Models\MembershipCategory;
use App\Models\User;
use App\Notifications\MembershipApplicationSubmitted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MembershipApplicationTest extends TestCase
{
    use DatabaseMigrations;

    private function category(): MembershipCategory
    {
        return MembershipCategory::query()
            ->where('name', 'Student')
            ->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'submission_key' => (string) Str::uuid(),
            'membership_category_id' => $this->category()->id,
            'full_name' => 'Alex Santos',
            'email' => 'alex@example.test',
            'phone' => '09171234567',
            'address' => 'Manila, Philippines',
            'latitude' => '14.5995000',
            'longitude' => '120.9842000',
            'privacy_consent' => '1',
            'website' => '',
        ], $overrides);
    }

    private function useDatabaseQueue(): void
    {
        config([
            'queue.default' => 'database',
            'queue.connections.database.connection' => 'sqlite',
            'queue.connections.database.after_commit' => false,
            'queue.failed.database' => 'sqlite',
            'mail.default' => 'array',
        ]);
    }

    public function test_public_form_lists_only_active_categories(): void
    {
        MembershipCategory::create([
            'name' => 'Hidden category',
            'fee' => '0.00',
            'is_active' => false,
        ]);

        $this->get(route('membership.create'))
            ->assertOk()
            ->assertSee('Membership application')
            ->assertSee('Student')
            ->assertDontSee('Hidden category')
            ->assertSee('name="privacy_consent"', false)
            ->assertSee('id="latitude"', false)
            ->assertSee('id="longitude"', false);
    }

    public function test_valid_application_is_saved_and_notification_dispatched(): void
    {
        Notification::fake();

        $response = $this->post(
            route('membership.store'),
            $this->payload()
        );

        $response->assertRedirect(route('membership.submitted'));

        $application = MembershipApplication::sole();

        $this->assertSame('pending', $application->status);
        $this->assertSame('Alex Santos', $application->full_name);
        $this->assertSame('14.5995000', $application->latitude);
        $this->assertSame('120.9842000', $application->longitude);
        $this->assertNotNull($application->verification_sent_at);
        $this->assertNotNull($application->privacy_consented_at);

        $this->assertMatchesRegularExpression(
            '/\AAPP-[0-9A-HJKMNP-TV-Z]{26}\z/',
            $application->reference_no
        );

        $response->assertSessionHas(
            'application_reference',
            $application->reference_no
        );

        Notification::assertSentTo(
            $application,
            MembershipApplicationSubmitted::class,
            fn ($notification): bool =>
                $notification->referenceNo === $application->reference_no
                && $notification->applicationStatus === 'pending'
                && $notification->afterCommit === true
        );

        $this->get(route('membership.submitted'))
            ->assertOk()
            ->assertSee($application->reference_no);
    }

    public static function invalidApplications(): array
    {
        return [
            'invalid email' => [
                ['email' => 'invalid-email'],
                'email',
            ],
            'long name' => [
                ['full_name' => str_repeat('A', 151)],
                'full_name',
            ],
            'long phone' => [
                ['phone' => str_repeat('1', 31)],
                'phone',
            ],
            'long address' => [
                ['address' => str_repeat('A', 256)],
                'address',
            ],
            'latitude outside range' => [
                ['latitude' => '91'],
                'latitude',
            ],
            'longitude outside range' => [
                ['longitude' => '181'],
                'longitude',
            ],
            'missing longitude' => [
                ['longitude' => null],
                'longitude',
            ],
            'missing consent' => [
                ['privacy_consent' => null],
                'privacy_consent',
            ],
            'honeypot filled' => [
                ['website' => 'https://spam.example'],
                'website',
            ],
            'status injection' => [
                ['status' => 'approved'],
                'status',
            ],
        ];
    }

    #[DataProvider('invalidApplications')]
    public function test_invalid_applications_are_rejected(
        array $overrides,
        string $field
    ): void {
        Notification::fake();

        $this->postJson(
            route('membership.store'),
            $this->payload($overrides)
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('membership_applications', 0);

        Notification::assertNothingSent();
    }

    public function test_inactive_category_is_rejected(): void
    {
        Notification::fake();

        $category = $this->category();
        $category->update(['is_active' => false]);

        $this->postJson(
            route('membership.store'),
            $this->payload(['membership_category_id' => $category->id])
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('membership_category_id');

        $this->assertDatabaseCount('membership_applications', 0);
        Notification::assertNothingSent();
    }

    public function test_same_form_submission_does_not_create_duplicates(): void
    {
        Notification::fake();

        $payload = $this->payload();

        $this->post(route('membership.store'), $payload)
            ->assertRedirect(route('membership.submitted'));

        $this->post(route('membership.store'), $payload)
            ->assertRedirect(route('membership.submitted'));

        $this->assertDatabaseCount('membership_applications', 1);
        Notification::assertCount(1);
    }

    public function test_separate_applications_have_unique_references(): void
    {
        Notification::fake();

        $this->post(route('membership.store'), $this->payload());
        $this->post(route('membership.store'), $this->payload());

        $references = MembershipApplication::pluck('reference_no');

        $this->assertCount(2, $references);
        $this->assertCount(2, $references->unique());
    }

    public function test_staff_pages_require_admin_authorization(): void
    {
        Notification::fake();

        $this->post(route('membership.store'), $this->payload());

        $application = MembershipApplication::sole();

        $this->get(route('applications.index'))
            ->assertRedirect(route('login'));

        $this->get(route('applications.show', $application))
            ->assertRedirect(route('login'));

        $ordinaryUser = User::factory()->create();

        $this->actingAs($ordinaryUser)
            ->get(route('applications.index'))
            ->assertForbidden();

        $this->get(route('applications.show', $application))
            ->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertSee($application->reference_no);

        $this->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee($application->email);
    }

    public function test_job_is_queued_only_after_outer_transaction_commits(): void
    {
        $this->useDatabaseQueue();

        DB::beginTransaction();

        try {
            $this->post(route('membership.store'), $this->payload())
                ->assertRedirect(route('membership.submitted'));

            $this->assertDatabaseCount('membership_applications', 1);
            $this->assertDatabaseCount('jobs', 0);

            DB::commit();

            $this->assertDatabaseCount('jobs', 1);
            $this->assertDatabaseHas('jobs', [
                'queue' => 'membership-mail',
            ]);
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    public function test_rollback_discards_application_and_queued_notification(): void
    {
        $this->useDatabaseQueue();

        DB::beginTransaction();

        try {
            $this->post(route('membership.store'), $this->payload())
                ->assertRedirect(route('membership.submitted'));

            $this->assertDatabaseCount('jobs', 0);

            DB::rollBack();

            $this->assertDatabaseCount('membership_applications', 0);
            $this->assertDatabaseCount('jobs', 0);
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    public function test_worker_processes_notification_with_safe_array_mailer(): void
    {
        $this->useDatabaseQueue();

        $this->post(route('membership.store'), $this->payload())
            ->assertRedirect(route('membership.submitted'));

        $this->assertDatabaseCount('jobs', 1);

        $this->artisan('queue:work', [
            'connection' => 'database',
            '--queue' => 'membership-mail',
            '--once' => true,
            '--tries' => 1,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);

        $messages = Mail::mailer('array')
            ->getSymfonyTransport()
            ->messages();

        $this->assertCount(1, $messages);
    }

    public function test_form_contains_map_and_weather_without_private_weather_key(): void
{
    config([
        'services.google_maps.key' => 'public-browser-test-key',
        'services.openweather.key' => 'private-weather-test-key',
    ]);

    $this->get(route('membership.create'))
        ->assertOk()
        ->assertSee('id="membership-map"', false)
        ->assertSee('id="weather-panel"', false)
        ->assertSee('data-maps-key="public-browser-test-key"', false)
        ->assertDontSee('private-weather-test-key');
}

public function test_application_can_be_submitted_without_optional_location(): void
{
    Notification::fake();

    $this->post(
        route('membership.store'),
        $this->payload([
            'latitude' => null,
            'longitude' => null,
        ])
    )->assertRedirect(route('membership.submitted'));

    $application = MembershipApplication::sole();

    $this->assertNull($application->latitude);
    $this->assertNull($application->longitude);
    $this->assertSame('pending', $application->status);
}

public function test_coordinates_are_preserved_after_other_validation_errors(): void
{
    Notification::fake();

    $this->from(route('membership.create'))
        ->post(
            route('membership.store'),
            $this->payload(['email' => 'invalid-email'])
        )
        ->assertRedirect(route('membership.create'))
        ->assertSessionHasErrors('email')
        ->assertSessionHasInput('latitude', '14.5995000')
        ->assertSessionHasInput('longitude', '120.9842000');

    $this->get(route('membership.create'))
        ->assertOk()
        ->assertSee('value="14.5995000"', false)
        ->assertSee('value="120.9842000"', false);

    $this->assertDatabaseCount('membership_applications', 0);
    Notification::assertNothingSent();
}
}