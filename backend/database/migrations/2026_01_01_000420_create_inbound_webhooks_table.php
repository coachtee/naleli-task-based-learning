<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->string('event_type', 64)->nullable();
            $table->string('external_id', 120)->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->json('payload');

            // morphTo: an Application or a Payment, once interpreted.
            $table->string('related_type', 64)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();

            $table->index(['source', 'external_id']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_webhooks');
    }
};
