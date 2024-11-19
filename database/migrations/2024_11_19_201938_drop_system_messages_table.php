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
        Schema::dropIfExists('system_message_ministry');
        Schema::dropIfExists('system_messages');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('system_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('icm_error_code')->nullable();
            $table->text('message_copy')->nullable();
            $table->text('view')->nullable();
            $table->text('fix')->nullable();
            $table->text('explanation')->nullable();
            $table->text('business_rule')->nullable();
            $table->integer('rule_number')->nullable();
            $table->text('reference')->nullable();
            $table->timestamps();
        });

        Schema::create('system_message_ministry', function (Blueprint $table) {
            $table->foreignId('system_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ministry_id')->constrained()->cascadeOnDelete();
            $table->primary(['system_message_id', 'ministry_id']);
        });
    }
};
