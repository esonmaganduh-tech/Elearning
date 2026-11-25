<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->date('membership_started_on')->nullable();
            $table->date('membership_expires_on')->nullable();
        });

        Schema::create('membership_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('membership_category_id')
                ->constrained('membership_categories')
                ->restrictOnDelete();

            $table->foreignId('membership_level_id')
                ->constrained('membership_levels')
                ->restrictOnDelete();

            $table->string('reference_no', 30)->unique();
            $table->string('type', 20)->default('membership');
            $table->string('status', 20)->default('pending');

            $table->string('member_name', 161);
            $table->string('membership_no', 30);
            $table->string('category_name');
            $table->string('level_name');

            $table->decimal('category_fee', 10, 2);
            $table->decimal('level_fee', 10, 2);
            $table->decimal('total_amount', 12, 2);

            $table->unsignedInteger('validity_days');
            $table->text('benefits')->nullable();

            $table->date('starts_on')->nullable();
            $table->date('expires_on')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->dateTime('approved_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('membership_transaction_id')
                ->constrained('membership_transactions')
                ->restrictOnDelete();

            $table->string('reference_no', 30)->unique();
            $table->uuid('idempotency_key')->unique();

            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->string('external_reference', 100)->nullable();

            $table->foreignId('received_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->dateTime('paid_at');
            $table->timestamps();

            $table->unique(['method', 'external_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('membership_transactions');

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'membership_started_on',
                'membership_expires_on',
            ]);
        });
    }
};