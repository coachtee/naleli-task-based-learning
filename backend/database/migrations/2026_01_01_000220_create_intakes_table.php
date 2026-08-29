<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->date('applications_open_on')->nullable();
            $table->date('applications_close_on')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('status', 16)->default('planned')->index();
            $table->timestamps();

            $table->unique(['programme_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intakes');
    }
};
