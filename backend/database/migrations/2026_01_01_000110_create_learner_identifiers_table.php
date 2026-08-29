<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('value_normalised', 190);
            $table->timestamps();

            // An old email address keeps resolving to the same person after
            // they change it, which is most of what stops duplicate learners.
            $table->unique(['type', 'value_normalised']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_identifiers');
    }
};
