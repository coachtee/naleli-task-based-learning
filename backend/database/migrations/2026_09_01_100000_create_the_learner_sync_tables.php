<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The learner's record, held by the school rather than by a device.
 *
 * A phone and a lab PC are both working copies. This is the original: what
 * the learner has worked through, what they have submitted, and the files
 * they produced. Every row hangs off `learner_id`, never off a device — which
 * is what lets the same person work at home on Android, walk into KCS, sit at
 * whichever machine is free, and carry on.
 *
 * The split inside `learner_submissions` is the important one. The columns
 * above the line are what the learner says they did and a device may write;
 * the columns below are what it counts for and only the school may write.
 * Keeping that boundary in the table rather than in a policy is what stops a
 * modified client marking itself competent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_sub_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();

            $table->string('sub_step_id', 120);
            $table->string('task_id', 120);

            $table->boolean('complete')->default(false);
            // The learner's clock, not ours: when they say they finished it.
            $table->timestamp('completed_at')->nullable();
            // Recorded but not trusted for merging — lab clocks drift. See
            // ProgressSynchroniser for why completion is a ratchet instead.
            $table->timestamp('client_updated_at')->nullable();
            $table->string('last_device', 120)->nullable();
            $table->timestamps();

            $table->unique(['learner_id', 'programme_id', 'sub_step_id'], 'learner_sub_steps_unique');
            $table->index(['learner_id', 'task_id']);
        });

        Schema::create('learner_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();

            $table->string('task_id', 120);

            // --- what the learner did. A device may write these. ----------
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedTinyInteger('confidence_rating')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->string('last_device', 120)->nullable();

            // --- what it counts for. Only the school writes these. --------
            $table->string('result', 24)->default('not_yet_assessed')->index();
            $table->timestamp('assessed_at')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('feedback')->nullable();

            $table->timestamps();

            $table->unique(['learner_id', 'programme_id', 'task_id'], 'learner_submissions_unique');
        });

        Schema::create('learner_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();

            $table->string('task_id', 120);
            // The client's own id for this file. Uploading is idempotent on
            // it, so a retry after a dropped connection cannot double a photo.
            $table->string('client_evidence_id', 64);

            $table->string('file_name', 200);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('byte_size');
            $table->string('checksum', 64)->nullable();

            $table->string('disk', 32);
            $table->string('storage_path', 320);

            $table->text('description')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('received_at');
            $table->string('last_device', 120)->nullable();
            $table->timestamps();

            $table->unique(['learner_id', 'client_evidence_id'], 'learner_evidence_unique');
            $table->index(['learner_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_evidence');
        Schema::dropIfExists('learner_submissions');
        Schema::dropIfExists('learner_sub_steps');
    }
};
