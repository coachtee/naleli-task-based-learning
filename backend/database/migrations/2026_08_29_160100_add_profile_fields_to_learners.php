<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The detail that used to sit on the front of a long application form.
 *
 * Every column here is nullable on purpose. None of it is asked before a place
 * is held; it is collected afterwards, from someone who has already committed,
 * which is the whole point of the change. ProfileCompleteness reads these to
 * say what is still owed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table): void {
            $table->string('address_line', 160)->nullable()->after('whatsapp');
            $table->string('suburb', 80)->nullable()->after('address_line');
            $table->string('city', 80)->nullable()->after('suburb');
            $table->string('province', 60)->nullable()->after('city');
            $table->string('postal_code', 12)->nullable()->after('province');

            $table->string('highest_qualification', 120)->nullable()->after('date_of_birth');
            $table->string('school_or_institution', 160)->nullable()->after('highest_qualification');
            $table->string('employment_status', 60)->nullable()->after('school_or_institution');

            $table->timestamp('profile_completed_at')->nullable()->after('identity_verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table): void {
            $table->dropColumn([
                'address_line',
                'suburb',
                'city',
                'province',
                'postal_code',
                'highest_qualification',
                'school_or_institution',
                'employment_status',
                'profile_completed_at',
            ]);
        });
    }
};
