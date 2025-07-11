<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $table = 'button_input_form_elements';
    protected $column = 'button_type';
    protected $enumValues = [
        'primary',
        'secondary',
        'danger',
        'ghost',
        'danger--primary',
        'danger--ghost',
        'danger--tertiary',
        'tertiary',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add enum column with new set and migrate values
        Schema::table($this->table, function (Blueprint $table) {
            $table->enum("{$this->column}_new", $this->enumValues)
                ->default('ghost')
                ->after($this->column);
        });

        // Copy over existing values (submit, reset, button) into closest new values
        DB::table($this->table)->update([
            "{$this->column}_new" => DB::raw("CASE
                WHEN {$this->column} = 'submit' THEN 'primary'
                WHEN {$this->column} = 'reset' THEN 'secondary'
                WHEN {$this->column} = 'button' THEN 'ghost'
                ELSE 'ghost' END")
        ]);

        // Drop old column, rename new to original
        Schema::table($this->table, function (Blueprint $table) {
            $table->dropColumn($this->column);
            $table->renameColumn("{$this->column}_new", $this->column);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate old enum with original values
        Schema::table($this->table, function (Blueprint $table) {
            $table->enum("{$this->column}_old", [
                'submit',
                'reset',
                'button',
            ])->default('button')->after($this->column);
        });

        // Map new values back to original ones
        DB::table($this->table)->update([
            "{$this->column}_old" => DB::raw("CASE
                WHEN {$this->column} = 'primary' THEN 'submit'
                WHEN {$this->column} = 'secondary' THEN 'reset'
                ELSE 'button' END")
        ]);

        Schema::table($this->table, function (Blueprint $table) {
            $table->dropColumn($this->column);
            $table->renameColumn("{$this->column}_old", $this->column);
        });
    }
};
