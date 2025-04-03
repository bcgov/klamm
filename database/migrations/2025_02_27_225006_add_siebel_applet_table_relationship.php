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
        Schema::disableForeignKeyConstraints();

        Schema::create('bre_field_siebel_applet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bre_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('siebel_applet_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('bre_field_siebel_table', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bre_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('siebel_table_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bre_field_siebel_table');
        Schema::dropIfExists('bre_field_siebel_applet');
    }
};
