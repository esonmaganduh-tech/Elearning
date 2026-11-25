<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('actor_name');
            $table->string('actor_kind', 20);

            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->string('reference_no', 30)->nullable()->index();

            $table->string('action', 20);
            $table->json('changed_fields');
            $table->json('old_values');
            $table->json('new_values');

            $table->timestamp('created_at')->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};