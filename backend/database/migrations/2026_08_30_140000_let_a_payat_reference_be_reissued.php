<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cancelled or expired Pay@ reference has to be replaceable.
     *
     * Pay@ will not let an account number be reused, and ours were derived
     * from the invoice id alone — so once a reference was cancelled that
     * invoice could never be given a payable one again. Worse, the "adopt the
     * existing reference" recovery path adopted the dead one and showed it to
     * a registrar as if a learner could pay it.
     *
     * The attempt counter is what makes the derived scheme re-issuable, and
     * the last known state is what lets the dashboard say "cancelled — issue a
     * new one" without calling Pay@ on every page load.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedTinyInteger('payat_attempt')->default(0)->after('payat_account_number');
            $table->string('payat_state', 48)->nullable()->after('payat_attempt');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['payat_attempt', 'payat_state']);
        });
    }
};
