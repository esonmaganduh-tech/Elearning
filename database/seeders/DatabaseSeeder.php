<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['Juan', 'Dela Cruz', 'juan@example.com', 'Student', 'Basic'],
            ['Maria', 'Santos', 'maria@example.com', 'Corporate', 'Platinum'],
        ];

        foreach ($samples as [$first, $last, $email, $categoryName, $levelName]) {
            $category = MembershipCategory::where('name', $categoryName)
                ->firstOrFail();

            $level = MembershipLevel::where('name', $levelName)
                ->firstOrFail();

            Member::firstOrCreate(
                ['email' => $email],
                array_merge([
                    'first_name' => $first,
                    'last_name' => $last,
                    'membership_category_id' => $category->id,
                    'membership_level_id' => $level->id,
                    'status' => 'Active',
                    'joined_at' => now()->toDateString(),
                    'notes' => 'Sample member record.',
                ], $level->terms())
            );
        }
    }
}