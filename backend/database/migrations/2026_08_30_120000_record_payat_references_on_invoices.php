<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The payable reference a learner takes to the till.
     *
     * Kept on the invoice rather than derived on demand because the account
     * number is what an inbound Pay@ callback names, and because a reference
     * that changed between being quoted to a learner and being paid would be
     * unpayable. Allocated once, then never rewritten.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Ours: 14 digits at most, unique on the merchant account forever.
            $table->string('payat_account_number', 14)->nullable()->unique()->after('paid_at');

            // Theirs: the internal id, the number printed at the till, and the
            // QR link we send over WhatsApp.
            $table->string('payat_request_to_pay_id', 64)->nullable()->after('payat_account_number');
            $table->string('payat_source_reference', 64)->nullable()->after('payat_request_to_pay_id');
            $table->string('payat_payment_link', 255)->nullable()->after('payat_source_reference');
            $table->timestamp('payat_requested_at')->nullable()->after('payat_payment_link');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['payat_account_number']);
            $table->dropColumn([
                'payat_account_number',
                'payat_request_to_pay_id',
                'payat_source_reference',
                'payat_payment_link',
                'payat_requested_at',
            ]);
        });
    }
};
