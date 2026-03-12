<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Columns to convert: boolean → enum string.
     */
    protected array $columns = ['is_required', 'is_read_only'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add temporary string columns
        Schema::table('form_elements', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                $table->string("{$column}_new")->nullable()->after($column);
            }
        });

        // Step 2: Migrate data: true → 'always', false/null → null
        foreach ($this->columns as $column) {
            DB::table('form_elements')->chunkById(1000, function ($rows) use ($column) {
                foreach ($rows as $row) {
                    $newValue = match ($row->{$column}) {
                        true => 'always',
                        default => null,
                    };

                    DB::table('form_elements')
                        ->where('id', $row->id)
                        ->update(["{$column}_new" => $newValue]);
                }
            });
        }

        // Step 3: Drop old boolean columns
        Schema::table('form_elements', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                $table->dropColumn($column);
            }
        });

        // Step 4: Rename new columns to original names
        Schema::table('form_elements', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                $table->renameColumn("{$column}_new", $column);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add temporary boolean columns
        Schema::table('form_elements', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                $table->boolean("{$column}_new")->default(false)->after($column);
            }
        });

        // Step 2: Reverse migrate: 'always' → true, null → false
        foreach ($this->columns as $column) {
            DB::table('form_elements')->chunkById(1000, function ($rows) use ($column) {
                foreach ($rows as $row) {
                    $newValue = $row->{$column} === 'always';

                    DB::table('form_elements')
                        ->where('id', $row->id)
                        ->update(["{$column}_new" => $newValue]);
                }
            });
        }

        // Step 3: Drop enum columns
        Schema::table('form_elements', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                $table->dropColumn($column);
            }
        });

        // Step 4: Rename boolean columns back
        Schema::table('form_elements', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                $table->renameColumn("{$column}_new", $column);
            }
        });
    }
};