<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private function signInAsAdmin(): void
    {
        $this->actingAs(
            User::factory()->create(['is_admin' => true])
        );
    }

    private function catalogCases(): array
    {
        return [
            [
                'categories',
                MembershipCategory::class,
                [
                    'name' => 'Alumni',
                    'level' => 'Community',
                    'fee' => '100.25',
                    'is_active' => 1,
                ],
            ],
            [
                'levels',
                MembershipLevel::class,
                [
                    'name' => 'Diamond',
                    'fee' => '3500.25',
                    'validity_days' => 365,
                    'benefits' => 'Priority support',
                    'is_active' => 1,
                ],
            ],
        ];
    }

    private function existingMember(): Member
    {
        $category = MembershipCategory::where('name', 'Student')
            ->firstOrFail();

        $level = MembershipLevel::where('name', 'Basic')
            ->firstOrFail();

        return Member::create([
            'first_name' => 'Original',
            'last_name' => 'Member',
            'email' => 'original@example.com',
            'membership_category_id' => $category->id,
            'membership_level_id' => $level->id,
            'fee' => '777.25',
            'validity_period' => 'Original validity',
            'benefits' => 'Original benefits',
            'status' => 'Inactive',
            'joined_at' => '2026-11-25',
        ]);
    }

    private function memberInput(Member $member): array
    {
        return [
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'membership_category_id' => $member->membership_category_id,
            'membership_level_id' => $member->membership_level_id,
            'status' => $member->status,
            'joined_at' => $member->joined_at->toDateString(),
        ];
    }

    public function test_admin_can_manage_unused_categories_and_levels(): void
    {
        $this->signInAsAdmin();

        foreach ($this->catalogCases() as [$path, $model, $data]) {
            $this->get("/{$path}/create")->assertOk();

            $response = $this->post("/{$path}", $data);
            $record = $model::where('name', $data['name'])->firstOrFail();

            $response->assertRedirect(
                route($path.'.show', $record)
            )->assertSessionHasNoErrors();

            $this->get("/{$path}")
                ->assertOk()
                ->assertSee($data['name']);

            $this->get("/{$path}/{$record->id}")
                ->assertOk()
                ->assertSee($data['name']);

            $this->get("/{$path}/{$record->id}/edit")
                ->assertOk();

            $this->put("/{$path}/{$record->id}", $data)
                ->assertSessionHasNoErrors();

            $changes = array_replace($data, [
                'name' => $data['name'].' Updated',
                'fee' => '120.50',
                'is_active' => 0,
            ]);

            $this->put("/{$path}/{$record->id}", $changes)
                ->assertSessionHasNoErrors();

            $record->refresh();

            $this->assertSame($changes['name'], $record->name);
            $this->assertSame('120.50', $record->fee);
            $this->assertFalse($record->is_active);

            $this->delete("/{$path}/{$record->id}")
                ->assertRedirect("/{$path}");

            $this->assertModelMissing($record);
        }
    }

    public function test_invalid_names_fees_and_validity_are_rejected(): void
    {
        $this->signInAsAdmin();

        foreach ($this->catalogCases() as [$path, $model, $data]) {
            $existing = $model::firstOrFail();

            $this->post("/{$path}", array_replace($data, [
                'name' => $existing->name,
            ]))->assertSessionHasErrors('name');

            $this->post("/{$path}", array_replace($data, [
                'fee' => '-1',
            ]))->assertSessionHasErrors('fee');

            $this->post("/{$path}", array_replace($data, [
                'fee' => '10.123',
            ]))->assertSessionHasErrors('fee');
        }

        $this->post('/levels', [
            'name' => 'Invalid duration',
            'fee' => 0,
            'validity_days' => 0,
            'is_active' => 1,
        ])->assertSessionHasErrors('validity_days');
    }

    public function test_assigned_options_cannot_be_deleted(): void
    {
        $this->signInAsAdmin();
        $member = $this->existingMember();

        $options = [
            ['categories', $member->membershipCategory],
            ['levels', $member->membershipLevel],
        ];

        foreach ($options as [$path, $record]) {
            $this->from("/{$path}/{$record->id}")
                ->delete("/{$path}/{$record->id}")
                ->assertRedirect("/{$path}/{$record->id}")
                ->assertSessionHasErrors('catalog');

            $this->assertModelExists($record);
        }

        $this->assertModelExists($member);
    }

    public function test_level_edits_preserve_old_terms_and_supply_new_terms(): void
    {
        $this->signInAsAdmin();

        $member = $this->existingMember();
        $number = $member->membership_no;
        $level = $member->membershipLevel;

        $this->put("/levels/{$level->id}", [
            'name' => $level->name,
            'fee' => '875.25',
            'validity_days' => 45,
            'benefits' => 'Updated benefits',
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $member->refresh();

        $this->assertSame('777.25', $member->fee);
        $this->assertSame('Original validity', $member->validity_period);
        $this->assertSame('Original benefits', $member->benefits);
        $this->assertSame($number, $member->membership_no);

        $this->post('/members', array_replace(
            $this->memberInput($member),
            ['email' => 'new@example.com', 'status' => 'Active']
        ))->assertSessionHasNoErrors();

        $newMember = Member::where('email', 'new@example.com')
            ->firstOrFail();

        $this->assertSame('875.25', $newMember->fee);
        $this->assertSame('45 days', $newMember->validity_period);
        $this->assertSame('Updated benefits', $newMember->benefits);
    }

    public function test_assigned_options_can_be_deactivated(): void
    {
        $this->signInAsAdmin();
        $member = $this->existingMember();

        $category = $member->membershipCategory;
        $level = $member->membershipLevel;

        $this->put("/categories/{$category->id}", [
            'name' => $category->name,
            'level' => $category->level,
            'fee' => $category->fee,
            'is_active' => 0,
        ])->assertSessionHasNoErrors();

        $this->put("/levels/{$level->id}", [
            'name' => $level->name,
            'fee' => $level->fee,
            'validity_days' => $level->validity_days,
            'benefits' => $level->benefits,
            'is_active' => 0,
        ])->assertSessionHasNoErrors();

        $this->get('/members/create')
            ->assertOk()
            ->assertDontSee('Student')
            ->assertDontSee('Basic');

        $this->get("/members/{$member->id}/edit")
            ->assertOk()
            ->assertSee('Student')
            ->assertSee('Basic');

        $this->assertSame(
            $category->id,
            $member->fresh()->membership_category_id
        );
    }

    public function test_empty_server_controlled_fields_are_rejected(): void
    {
        $this->signInAsAdmin();
        $member = $this->existingMember();

        $this->put("/members/{$member->id}", array_replace(
            $this->memberInput($member),
            [
                'membership_no' => null,
                'fee' => null,
                'validity_period' => null,
                'benefits' => null,
            ]
        ))->assertSessionHasErrors([
            'membership_no',
            'fee',
            'validity_period',
            'benefits',
        ]);

        $this->assertSame('777.25', $member->fresh()->fee);
        $this->assertSame('Original benefits', $member->fresh()->benefits);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach ($this->catalogCases() as [$path, $model, $data]) {
            $this->get("/{$path}")
                ->assertRedirect('/login');

            $this->post("/{$path}", $data)
                ->assertRedirect('/login');
        }
    }

    public function test_non_admin_accounts_cannot_manage_options(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ($this->catalogCases() as [$path, $model, $data]) {
            $record = $model::firstOrFail();

            $this->get("/{$path}")->assertForbidden();

            $this->post("/{$path}", $data)->assertForbidden();

            $this->put("/{$path}/{$record->id}", $data)
                ->assertForbidden();

            $this->delete("/{$path}/{$record->id}")
                ->assertForbidden();

            $this->assertModelExists($record);
        }
    }
}