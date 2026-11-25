<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('membership_no', 30)->nullable()->unique();

            $table->foreignId('membership_category_id')
                ->nullable()
                ->constrained('membership_categories')
                ->restrictOnDelete();

            $table->foreignId('membership_level_id')
                ->nullable()
                ->constrained('membership_levels')
                ->restrictOnDelete();
        });

        DB::transaction(function () {
            DB::table('members')
                ->orderBy('id')
                ->chunkById(100, function ($members) {
                    foreach ($members as $member) {
                        $categoryName = trim((string) $member->category);
                        $levelName = trim((string) $member->level);

                        $categoryName = $categoryName !== ''
                            ? $categoryName
                            : 'Imported category';

                        $levelName = $levelName !== ''
                            ? $levelName
                            : 'Imported level';

                        $categoryId = DB::table('membership_categories')
                            ->where('name', $categoryName)
                            ->value('id');

                        if ($categoryId === null) {
                            $categoryId = DB::table('membership_categories')
                                ->insertGetId([
                                    'name' => $categoryName,
                                    'fee' => 0,
                                    'is_active' => false,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                        }

                        $levelId = DB::table('membership_levels')
                            ->where('name', $levelName)
                            ->value('id');

                        if ($levelId === null) {
                            $levelId = DB::table('membership_levels')
                                ->insertGetId([
                                    'name' => $levelName,
                                    'fee' => $member->fee,
                                    'validity_days' => 365,
                                    'benefits' => $member->benefits,
                                    'is_active' => false,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                        }

                        DB::table('members')
                            ->where('id', $member->id)
                            ->update([
                                'membership_no' => 'MEM-'.Str::ulid(),
                                'membership_category_id' => $categoryId,
                                'membership_level_id' => $levelId,
                            ]);
                    }
                });
        });

        Schema::table('members', function (Blueprint $table) {
            $table->string('membership_no', 30)
                ->nullable(false)
                ->change();

            $table->unsignedBigInteger('membership_category_id')
                ->nullable(false)
                ->change();

            $table->unsignedBigInteger('membership_level_id')
                ->nullable(false)
                ->change();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['category', 'level']);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('category')->nullable();
            $table->string('level')->nullable();
        });

        DB::table('members')
            ->orderBy('id')
            ->chunkById(100, function ($members) {
                foreach ($members as $member) {
                    DB::table('members')
                        ->where('id', $member->id)
                        ->update([
                            'category' => DB::table('membership_categories')
                                ->where('id', $member->membership_category_id)
                                ->value('name'),
                            'level' => DB::table('membership_levels')
                                ->where('id', $member->membership_level_id)
                                ->value('name'),
                        ]);
                }
            });

        Schema::table('members', function (Blueprint $table) {
            $table->string('category')->nullable(false)->change();
            $table->string('level')->nullable(false)->change();

            $table->dropForeign(['membership_category_id']);
            $table->dropForeign(['membership_level_id']);
            $table->dropUnique(['membership_no']);

            $table->dropColumn([
                'membership_no',
                'membership_category_id',
                'membership_level_id',
            ]);
        });
    }
};