<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a programme is actually sold as.
 *
 * A programme is the education; an offering is the commercial package around
 * it — the price, how it is billed, how long access lasts, and what has to be
 * paid before it activates. Keeping them apart means a price change is a new
 * offering rather than a rewrite of what people already bought, and an
 * enrolment always points at the terms it was sold under.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();

            $table->string('code', 32)->unique();      // PPO-2027-BLOCK
            $table->string('name', 160);
            $table->string('description', 255)->nullable();

            // The commercial configuration. Invoices are derived from these
            // four columns, never supplied by whoever happens to be accepting
            // the application.
            $table->string('billing_model', 24);
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('deposit_cents')->nullable();
            $table->unsignedSmallInteger('instalment_count')->nullable();
            $table->char('currency', 3)->default('ZAR');

            // How long the entitlement lasts once activated. A three-month
            // block is 90 days here and one invoice above — the duration is a
            // property of access, not a reason to split the price.
            $table->unsignedSmallInteger('access_duration_days')->nullable();

            $table->string('activation_rule', 24)->default('on_first_payment');

            $table->string('status', 16)->default('draft')->index();
            $table->date('available_from')->nullable();
            $table->date('available_until')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('enrolments', function (Blueprint $table) {
            // The terms this enrolment was sold under. Nullable because
            // historical enrolments predate offerings, and because a bursary
            // or staff placement may have no commercial package at all.
            $table->foreignId('offering_id')->nullable()->after('application_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('programme_requirements', function (Blueprint $table) {
            // Certification is a human decision; completion is a click count.
            // Only the first may open a paid specialisation.
            $table->boolean('requires_certificate')->default(false)->after('rule_type');
        });
    }

    public function down(): void
    {
        Schema::table('programme_requirements', function (Blueprint $table) {
            $table->dropColumn('requires_certificate');
        });

        Schema::table('enrolments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offering_id');
        });

        Schema::dropIfExists('offerings');
    }
};
