<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every programme has to be traceable back to the page or form on kcs.edu.za
 * that publishes it.
 *
 * The audit that prompted this found the backend carrying a catalogue taken
 * from the site's navigation while every real application arrived naming
 * something from the application form instead — two lists that had never been
 * compared because nothing recorded where a programme came from. These columns
 * make that comparison possible, and CatalogueManifest enforces it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programmes', function (Blueprint $table): void {
            $table->string('source_url', 255)->nullable()->after('slug');
            $table->string('source_note', 160)->nullable()->after('source_url');
            $table->string('nqf_level', 12)->nullable()->after('tier');
        });
    }

    public function down(): void
    {
        Schema::table('programmes', function (Blueprint $table): void {
            $table->dropColumn(['source_url', 'source_note', 'nqf_level']);
        });
    }
};
