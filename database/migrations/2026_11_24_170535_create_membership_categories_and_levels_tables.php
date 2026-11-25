<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('level')->nullable();
            $table->decimal('fee', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('membership_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('fee', 10, 2)->default(0);
            $table->unsignedInteger('validity_days')->default(365);
            $table->text('benefits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $timestamp = now();

        foreach (['Student', 'Regular', 'Senior Citizen', 'Corporate'] as $name) {
            DB::table('membership_categories')->insert([
                'name' => $name,
                'fee' => 0,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $levels = [
            ['Basic', '499.00', 30],
            ['Silver', '999.00', 90],
            ['Gold', '1499.00', 180],
            ['Platinum', '2499.00', 365],
        ];

        foreach ($levels as [$name, $fee, $days]) {
            DB::table('membership_levels')->insert([
                'name' => $name,
                'fee' => $fee,
                'validity_days' => $days,
                'benefits' => null,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_levels');
        Schema::dropIfExists('membership_categories');
    }
};