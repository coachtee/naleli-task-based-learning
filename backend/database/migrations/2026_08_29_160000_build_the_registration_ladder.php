<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the admissions pipeline into one registration ladder that a Facebook
 * lead, a website registration and a walk-in all climb.
 *
 * Three things change. Two statuses are renamed to say what they are — a
 * person registers, they do not apply and wait. Lead capture gains its own
 * rungs so a campaign contact is the same record as the student they become.
 * And how the registration is being paid for is asked once, here, instead of
 * in a separate funding form.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const RENAMED = [
        'applied' => 'registration_started',
        'awaiting_identity' => 'profile_incomplete',
        'enrolled' => 'registered',
    ];

    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('funding_source', 40)->nullable()->after('status');
            $table->string('funding_status', 40)->nullable()->after('funding_source');
            $table->text('funding_note')->nullable()->after('funding_status');

            // Where a lead actually came from, beyond which system carried it:
            // the campaign or referral that earned the contact.
            $table->string('campaign', 120)->nullable()->after('source_reference');

            $table->timestamp('first_contacted_at')->nullable()->after('applied_at');
            $table->timestamp('registered_at')->nullable()->after('decided_at');
        });

        foreach (self::RENAMED as $from => $to) {
            DB::table('applications')->where('status', $from)->update(['status' => $to]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMED as $from => $to) {
            DB::table('applications')->where('status', $to)->update(['status' => $from]);
        }

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn([
                'funding_source',
                'funding_status',
                'funding_note',
                'campaign',
                'first_contacted_at',
                'registered_at',
            ]);
        });
    }
};
