<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained();
            $table->foreignId('enrolment_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('sequence')->default(1);
            $table->string('description', 160);
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('ZAR');
            $table->date('due_on')->nullable();

            // The one flag that keeps the commercial model reversible: whichever
            // invoice carries it is the one whose settlement activates the
            // enrolment. One-time, block and deposit-plus-monthly are all just
            // different row counts against this table.
            $table->boolean('activates_enrolment')->default(false);

            $table->string('status', 16)->default('due')->index();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
