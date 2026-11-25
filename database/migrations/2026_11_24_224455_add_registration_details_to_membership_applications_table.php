<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->foreignId('member_id')
                ->nullable()
                ->unique()
                ->constrained('members')
                ->restrictOnDelete();

            $table->foreignId('registered_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('registered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->dropForeign(['registered_by']);
            $table->dropUnique(['member_id']);

            $table->dropColumn([
                'member_id',
                'registered_by',
                'registered_at',
            ]);
        });
    }
};