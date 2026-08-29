<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 160);
            $table->string('slug', 160)->unique();
            $table->string('tier', 24)->index();
            $table->string('summary', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('duration_label', 80)->nullable();
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->string('weekly_hours', 24)->nullable();

            // Display text only. Structured pricing waits for the confirmed
            // commercial model — encoding one now would invent the decision
            // that is deliberately still open.
            $table->string('fee_note', 160)->nullable();

            $table->string('content_code', 64)->nullable();
            $table->string('content_version', 32)->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmes');
    }
};
