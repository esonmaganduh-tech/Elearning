<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(
            User::factory()->create(['is_admin' => true])
        );
    }

    private function memberData(array $changes = []): array
    {
        return array_replace([
            'first_name' => 'Laptop',
            'last_name' => 'Test',
            'email' => 'laptop@example.com',
            'membership_category_id' => MembershipCategory::where(
                'name',
                'Student'
            )->firstOrFail()->id,
            'membership_level_id' => MembershipLevel::where(
                'name',
                'Basic'
            )->firstOrFail()->id,
            'status' => 'Active',
            'joined_at' => '2026-11-25',
            'notes' => 'Test record',
        ], $changes);
    }

    public function test_new_members_get_unique_numbers_and_level_terms(): void
    {
        $this->post('/members', $this->memberData())
            ->assertSessionHasNoErrors();

        $this->post('/members', $this->memberData([
            'email' => 'second@example.com',
        ]))->assertSessionHasNoErrors();

        $members = Member::orderBy('id')->get();

        $this->assertCount(2, $members);
        $this->assertNotSame(
            $members[0]->membership_no,
            $members[1]->membership_no
        );
        $this->assertStringStartsWith('MEM-', $members[0]->membership_no);
        $this->assertSame('499.00', $members[0]->fee);
        $this->assertSame('30 days', $members[0]->validity_period);
    }

    public function test_duplicate_email_is_rejected_but_own_email_can_be_kept(): void
    {
        $data = $this->memberData();

        $this->post('/members', $data)
            ->assertSessionHasNoErrors();

        $this->post('/members', $data)
            ->assertSessionHasErrors('email');

        $member = Member::sole();

        $this->put("/members/{$member->id}", $data)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('members', 1);
    }

    public function test_inactive_choices_cannot_be_new_assignments(): void
    {
        $data = $this->memberData();

        MembershipCategory::findOrFail(
            $data['membership_category_id']
        )->update(['is_active' => false]);

        MembershipLevel::findOrFail(
            $data['membership_level_id']
        )->update(['is_active' => false]);

        $this->post('/members', $data)
            ->assertSessionHasErrors([
                'membership_category_id',
                'membership_level_id',
            ]);

        $this->assertDatabaseCount('members', 0);
    }

    public function test_existing_inactive_assignments_can_be_retained(): void
    {
        $data = $this->memberData();

        $this->post('/members', $data)
            ->assertSessionHasNoErrors();

        $member = Member::sole();

        $member->membershipCategory->update(['is_active' => false]);
        $member->membershipLevel->update(['is_active' => false]);

        $this->put("/members/{$member->id}", array_replace($data, [
            'first_name' => 'Updated',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Updated', $member->fresh()->first_name);
    }

    public function test_numbers_and_fees_cannot_be_supplied_by_the_form(): void
    {
        $this->post('/members', $this->memberData([
            'membership_no' => 'MEM-FORCED',
            'fee' => 1,
        ]))->assertSessionHasErrors(['membership_no', 'fee']);

        $this->assertDatabaseCount('members', 0);
    }

    public function test_ordinary_edits_preserve_terms_and_level_changes_replace_them(): void
    {
        $data = $this->memberData();

        $this->post('/members', $data)
            ->assertSessionHasNoErrors();

        $member = Member::sole();
        $originalNumber = $member->membership_no;

        $member->membershipLevel->update([
            'fee' => '850.00',
            'validity_days' => 60,
        ]);

        $this->put("/members/{$member->id}", $data)
            ->assertSessionHasNoErrors();

        $this->assertSame('499.00', $member->fresh()->fee);
        $this->assertSame('30 days', $member->fresh()->validity_period);

        $silver = MembershipLevel::where('name', 'Silver')->firstOrFail();

        $this->put("/members/{$member->id}", array_replace($data, [
            'membership_level_id' => $silver->id,
        ]))->assertSessionHasNoErrors();

        $member->refresh();

        $this->assertSame('999.00', $member->fee);
        $this->assertSame('90 days', $member->validity_period);
        $this->assertSame($originalNumber, $member->membership_no);
    }

    public function test_referenced_category_cannot_be_deleted(): void
    {
        $this->post('/members', $this->memberData())
            ->assertSessionHasNoErrors();

        $category = Member::sole()->membershipCategory;

        $this->expectException(QueryException::class);

        $category->delete();
    }
}