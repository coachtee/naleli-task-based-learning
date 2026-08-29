<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three staff roles need a column, not a permissions package. Assessor and
 * moderator arrive in Phase 4, and that is the right moment to bring in
 * spatie/laravel-permission — not now, for three values.
 *
 * Learners never get a row in `users`. They are learners, authenticated by
 * device token, and they never see the dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('registrar')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
