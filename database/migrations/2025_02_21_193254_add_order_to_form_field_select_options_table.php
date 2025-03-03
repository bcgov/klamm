<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('select_option_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('select_option_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_field_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('form_instance_field_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('order');
            $table->timestamps();
        });

        // Seed the new table with data from the old table
        $oldData = DB::table('form_field_select_options')->get();
        foreach ($oldData as $index => $data) {
            DB::table('select_option_instances')->insert([
                'form_field_id' => $data->form_field_id,
                'select_option_id' => $data->select_options_id,
                'form_instance_field_id' => null,
                'order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Drop the old table
        Schema::dropIfExists('form_field_select_options');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Store data from the new table before dropping it
        $dataToRestore = DB::table('select_option_instances')->get();

        // Drop the new table
        Schema::dropIfExists('select_option_instances');

        // Recreate the old table
        Schema::create('form_field_select_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('select_options_id')->constrained()->onDelete('cascade');
        });

        // Insert the data back into the old table
        foreach ($dataToRestore as $data) {
            DB::table('form_field_select_options')->insert([
                'form_field_id' => $data->form_field_id,
                'select_options_id' => $data->select_option_id, // Use the correct column name
            ]);
        }
    }
};
