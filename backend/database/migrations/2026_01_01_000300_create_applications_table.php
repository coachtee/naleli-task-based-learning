<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->constrained();
            $table->foreignId('intake_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 24)->default('applied')->index();
            $table->string('source', 24);
            $table->unsignedInteger('source_form_id')->nullable();
            $table->string('source_reference', 64)->nullable();

            $table->json('payload')->nullable();
            $table->timestamp('applied_at');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision_note', 255)->nullable();
            $table->timestamps();

            // The webhook's idempotency guarantee: Fluent Forms retrying a
            // delivery writes nothing the second time.
            $table->unique(['source', 'source_form_id', 'source_reference'], 'applications_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
