<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('currency_input_form_elements', function (Blueprint $table) {
            $table->id();
            $table->string('labelText')->nullable(); 
            $table->boolean('enableVarSub')->default(false);
            $table->boolean('hideLabel')->default(false);
            $table->string('placeholder')->nullable();
            $table->string('defaultValue')->nullable();
            $table->string('min')->nullable();
            $table->string('max')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_input_form_elements');
    }
};
