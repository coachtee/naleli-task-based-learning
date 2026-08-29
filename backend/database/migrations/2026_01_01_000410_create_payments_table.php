<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('learner_id')->constrained();

            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('ZAR');

            // A plain string, not an enum: providers are added by configuration
            // as merchant accounts are approved, never by a schema change.
            $table->string('provider', 32)->index();
            $table->string('provider_reference', 120)->nullable();

            $table->string('status', 16)->default('initiated')->index();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            // What makes a replayed webhook harmless. Repeated NULLs are
            // permitted in a unique index, so manual receipts with no reference
            // coexist with de-duplicated gateway callbacks.
            $table->unique(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
