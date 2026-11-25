<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->decimal('fee', 10, 2)->default(0)->after('level');
            $table->string('validity_period')->default('Not specified')->after('fee');
            $table->text('benefits')->nullable()->after('validity_period');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['fee', 'validity_period', 'benefits']);
        });
    }
};
