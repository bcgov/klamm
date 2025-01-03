<?php

use App\Models\SelectOptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('form_field_select_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('select_options_id')->constrained()->onDelete('cascade');
        });

        $selectOptions = SelectOptions::all();

        foreach ($selectOptions as $option) {
            if ($option->form_field_id) {
                DB::table('form_field_select_options')->insert([
                    'form_field_id' => $option->form_field_id,
                    'select_options_id' => $option->id,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_field_select_options');
    }
};
