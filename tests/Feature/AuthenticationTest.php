<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'AdminTestPass123!';

    private function createAdmin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'password' => self::PASSWORD,
        ]);
    }

    public function test_login_page_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Staff login');
    }

    public function test_guests_cannot_access_member_pages(): void
    {
        $this->get('/members')
            ->assertRedirect('/login');

        $this->get('/members/create')
            ->assertRedirect('/login');

        $this->post('/members', [])
            ->assertRedirect('/login');
    }

    public function test_administrator_can_log_in_and_view_members(): void
    {
        $admin = $this->createAdmin();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/members');

        $this->assertAuthenticatedAs($admin);

        $this->get('/members')
            ->assertOk()
            ->assertSee('Member Management');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $admin = $this->createAdmin();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'WrongPassword123!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_ordinary_account_cannot_use_staff_login(): void
    {
        $user = User::factory()->create([
            'password' => self::PASSWORD,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_non_administrator_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/members')
            ->assertForbidden();
    }

    public function test_administrator_can_log_out(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();

        $this->get('/members')
            ->assertRedirect('/login');
    }

    public function test_login_submissions_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post('/login', [
                'email' => 'missing@example.com',
                'password' => 'WrongPassword123!',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/login', [
            'email' => 'missing@example.com',
            'password' => 'WrongPassword123!',
        ])->assertStatus(429);
    }

    public function test_command_creates_an_administrator_with_hashed_password(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Administrator name', 'Test Administrator')
            ->expectsQuestion('Administrator email', 'admin@example.com')
            ->expectsQuestion('Password', self::PASSWORD)
            ->expectsQuestion('Confirm password', self::PASSWORD)
            ->expectsOutput('Administrator created successfully.')
            ->assertSuccessful();

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue($admin->is_admin);
        $this->assertTrue(
            Hash::check(self::PASSWORD, $admin->password)
        );
    }
}
