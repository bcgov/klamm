<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('checkbox_group_form_elements', function (Blueprint $table) {
            $table->id();
            $table->string('labelText')->nullable(); 
            $table->boolean('hideLabel')->default(false);
            $table->json('defaultSelected')->nullable();
            $table->boolean('enableVarSub')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkbox_group_form_elements');
    }
};
