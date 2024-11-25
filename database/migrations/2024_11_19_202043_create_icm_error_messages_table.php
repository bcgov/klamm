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
        Schema::create('icm_error_messages', function (Blueprint $table) {
            $table->id();
            $table->string('icm_error_code')->nullable();
            $table->text('business_rule')->nullable();
            $table->integer('rule_number')->nullable();
            $table->text('message_copy')->nullable();
            $table->text('fix')->nullable();
            $table->text('explanation')->nullable();
            $table->text('reference')->nullable();
            $table->timestamps();
        });

        Schema::create('icm_error_message_ministry', function (Blueprint $table) {
            $table->foreignId('icm_error_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ministry_id')->constrained()->cascadeOnDelete();
            $table->primary(['icm_error_message_id', 'ministry_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('icm_error_message_ministry');
        Schema::dropIfExists('icm_error_messages');
    }
};
