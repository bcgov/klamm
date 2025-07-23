<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\FormBuilding\FormVersion;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('form_versions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->index('uuid');
        });

        // Backfill existing form versions with UUIDs
        FormVersion::whereNull('uuid')->chunkById(100, function ($formVersions) {
            foreach ($formVersions as $formVersion) {
                $formVersion->uuid = (string) Str::uuid();
                $formVersion->save();
            }
        });

        // Make the uuid field non-nullable after backfilling
        Schema::table('form_versions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_versions', function (Blueprint $table) {
            $table->dropIndex(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
