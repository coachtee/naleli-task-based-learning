<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a learner logs in at a shared lab PC.
 *
 * The phone has an access token redeemed once for a device token, and that is
 * right for a phone: one learner, one handset, for good. A lab machine is the
 * opposite — three learners a day, every day, and whatever the last one used
 * must not still be sitting there for the next one. So the PC gets a login
 * instead of an activation: a learner reference and a PIN, exchanged for a
 * session that dies when they log out.
 *
 * Six digits rather than four. A learner reference is guessable (they run in
 * sequence), so the PIN is the only real secret, and 10,000 combinations
 * behind a rate limit is thinner than it looks when a classmate can watch you
 * type it twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->string('pin_hash')->nullable()->after('status');
            $table->timestamp('pin_set_at')->nullable()->after('pin_hash');
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'pin_set_at']);
        });
    }
};
