<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MemberMigrationTest extends TestCase
{
    public function test_existing_member_details_survive_conversion(): void
    {
        $linkMigration = null;

        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (str_ends_with(
                $file,
                '_link_members_to_membership_catalogs.php'
            )) {
                $linkMigration = require $file;
                break;
            }

            (require $file)->up();
        }

        $this->assertNotNull($linkMigration);

        $id = DB::table('members')->insertGetId([
            'first_name' => 'Existing',
            'last_name' => 'Member',
            'email' => 'existing@example.com',
            'phone' => '09123456789',
            'category' => 'Student',
            'level' => 'Basic',
            'fee' => '777.25',
            'validity_period' => 'Original custom validity',
            'benefits' => 'Original member benefits',
            'status' => 'Active',
            'joined_at' => '2026-01-15',
            'notes' => 'Preserve this note',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $linkMigration->up();

        $member = DB::table('members')->find($id);

        $this->assertSame('Existing', $member->first_name);
        $this->assertSame(
            '777.25',
            number_format((float) $member->fee, 2, '.', '')
        );
        $this->assertSame(
            'Original custom validity',
            $member->validity_period
        );
        $this->assertSame(
            'Original member benefits',
            $member->benefits
        );
        $this->assertSame('Preserve this note', $member->notes);
        $this->assertStringStartsWith('MEM-', $member->membership_no);

        $this->assertSame(
            'Student',
            DB::table('membership_categories')
                ->where('id', $member->membership_category_id)
                ->value('name')
        );

        $this->assertSame(
            'Basic',
            DB::table('membership_levels')
                ->where('id', $member->membership_level_id)
                ->value('name')
        );
    }
}