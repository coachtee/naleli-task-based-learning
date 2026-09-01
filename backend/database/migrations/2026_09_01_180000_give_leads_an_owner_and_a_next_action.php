<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What turns a list of names into admissions work.
 *
 * Eighty-five leads cost R191 and every one of them is worth R3,350 if they
 * register. The thing that loses them is not the ad and not the price — it is
 * nobody remembering to call back. So each lead gets one person who owns it,
 * a date it is next expected to be touched, and a log of everything anyone has
 * ever said to them.
 *
 * The log is the part that matters. A second call that opens with "sorry, who
 * am I speaking to?" undoes the first one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            // Not "the school" — a named person. A lead nobody owns is a lead
            // nobody calls.
            $table->foreignId('owner_id')->nullable()->after('campaign')
                ->constrained('users')->nullOnDelete();

            // The queue is ordered by this. Overdue first, oldest first.
            $table->timestamp('next_action_at')->nullable()->after('first_contacted_at')->index();
            $table->timestamp('last_touched_at')->nullable()->after('next_action_at');
            $table->unsignedSmallInteger('touch_count')->default(0)->after('last_touched_at');
        });

        Schema::create('lead_touches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('channel', 24);
            $table->string('outcome', 32)->index();
            $table->text('note')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['application_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_touches');

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn(['next_action_at', 'last_touched_at', 'touch_count']);
        });
    }
};
