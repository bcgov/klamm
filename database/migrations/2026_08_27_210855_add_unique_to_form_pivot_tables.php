<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    private function removeDuplicatesAndAddUniqueConstraint(string $tableName, array $columns): void
    {
        // Check if the table has an 'id' column. If not, use PostgreSQL's hidden 'ctid'
        $hasId = Schema::hasColumn($tableName, 'id');
        $keepColumn = $hasId ? 'id' : 'ctid';

        // Find all duplicate combinations and keep only the row with the lowest ID or ctid
        $duplicates = DB::table($tableName)
            ->select($columns[0], $columns[1], DB::raw("MIN({$keepColumn}) as keep_val"))
            ->groupBy($columns[0], $columns[1])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $deletedCount = 0;

        foreach ($duplicates as $duplicate) {
            // Build the where clause dynamically based on the columns
            $query = DB::table($tableName);
            foreach ($columns as $column) {
                $query->where($column, $duplicate->$column);
            }

            // Delete all rows with this combination, except the one we're keeping
            if ($hasId) {
                $query->where('id', '!=', $duplicate->keep_val);
            } else {
                // Use whereRaw for ctid since it's a PostgreSQL system column
                $query->whereRaw("ctid != ?", [$duplicate->keep_val]);
            }

            $deleted = $query->delete();
            $deletedCount += $deleted;
        }

        if ($deletedCount > 0) {
            Log::info("Removed {$deletedCount} duplicate entries from {$tableName} pivot table");
        }

        // Add unique constraint to the pivot table
        Schema::table($tableName, function (Blueprint $table) use ($columns) {
            $table->unique($columns);
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->removeDuplicatesAndAddUniqueConstraint('business_area_ministry', ['business_area_id', 'ministry_id']);
        $this->removeDuplicatesAndAddUniqueConstraint('business_area_user', ['business_area_id', 'user_id']);
        $this->removeDuplicatesAndAddUniqueConstraint('form_business_area', ['form_id', 'business_area_id']);
        $this->removeDuplicatesAndAddUniqueConstraint('form_form_location', ['form_id', 'form_location_id']);
        $this->removeDuplicatesAndAddUniqueConstraint('form_form_tags', ['form_id', 'form_tag_id']);
        $this->removeDuplicatesAndAddUniqueConstraint('form_repository_form', ['form_id', 'form_repository_id']);
        $this->removeDuplicatesAndAddUniqueConstraint('form_script_form_version', ['form_version_id', 'form_script_id']);
        $this->removeDuplicatesAndAddUniqueConstraint('form_user_type', ['form_id', 'user_type_id']);
        $this->removeDuplicatesAndAddUniqueConstraint('form_version_form_interfaces', ['form_version_id', 'form_interface_id']);
        $this->removeDuplicatesAndAddUniqueConstraint('form_versions_form_data_sources', ['form_version_id', 'form_data_source_id']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /**
         * Note: The duplicates removed in the up method cannot be restored
         */

        Schema::table('business_area_ministry', function (Blueprint $table) {
            $table->dropUnique(['business_area_id', 'ministry_id']);
        });

        Schema::table('business_area_user', function (Blueprint $table) {
            $table->dropUnique(['business_area_id', 'user_id']);
        });

        Schema::table('form_business_area', function (Blueprint $table) {
            $table->dropUnique(['form_id', 'business_area_id']);
        });

        Schema::table('form_form_location', function (Blueprint $table) {
            $table->dropUnique(['form_id', 'form_location_id']);
        });

        Schema::table('form_form_tags', function (Blueprint $table) {
            $table->dropUnique(['form_id', 'form_tag_id']);
        });

        Schema::table('form_repository_form', function (Blueprint $table) {
            $table->dropUnique(['form_id', 'form_repository_id']);
        });

        Schema::table('form_script_form_version', function (Blueprint $table) {
            $table->dropUnique(['form_version_id', 'form_script_id']);
        });

        Schema::table('form_user_type', function (Blueprint $table) {
            $table->dropUnique(['form_id', 'user_type_id']);
        });

        Schema::table('form_version_form_interfaces', function (Blueprint $table) {
            $table->dropUnique(['form_version_id', 'form_interface_id']);
        });

        Schema::table('form_versions_form_data_sources', function (Blueprint $table) {
            $table->dropUnique(['form_version_id', 'form_data_source_id']);
        });
    }
};