<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->constrained();
            $table->foreignId('intake_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 16)->default('pending')->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Repeating a programme in a later intake is a new row, so history
            // survives and the learner reference never changes.
            $table->unique(['learner_id', 'programme_id', 'intake_id'], 'enrolments_learner_programme_intake_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrolments');
    }
};
