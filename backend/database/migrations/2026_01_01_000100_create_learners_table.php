<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learners', function (Blueprint $table) {
            $table->id();

            // The permanent Naleli reference: NAL-2026-00001. The year is the
            // year of FIRST registration and is never recalculated, so a
            // learner who joins in 2026 and enrols again in 2028 keeps it.
            $table->string('learner_ref', 16)->unique();
            $table->unsignedSmallInteger('first_registered_year');

            $table->string('first_name', 80);
            $table->string('middle_name', 80)->nullable();
            $table->string('last_name', 80);
            $table->string('preferred_name', 80)->nullable();

            $table->string('email', 190)->nullable()->index();
            $table->string('phone', 24)->nullable()->index();
            $table->string('whatsapp', 24)->nullable();

            // Identification is optional at application and required before a
            // token is issued. The number is encrypted at rest; the hash is
            // what de-duplication matches on, so matching never needs the
            // plaintext decrypted or indexed.
            $table->string('id_type', 24)->nullable();
            $table->text('id_number_encrypted')->nullable();
            $table->char('id_number_hash', 64)->nullable()->unique();
            $table->string('id_number_masked', 24)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->timestamp('identity_verified_at')->nullable();
            $table->foreignId('identity_verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 24)->default('prospect')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learners');
    }
};
