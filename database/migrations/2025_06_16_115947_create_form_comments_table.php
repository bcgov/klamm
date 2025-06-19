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
        Schema::create('form_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_version_id')->constrained('form_versions')->onDelete('cascade');
            $table->foreignId('parent_comment_id')->nullable()->constrained('form_comments')->onDelete('cascade');
            $table->foreignId('element_id')->nullable()->constrained('form_fields')->onDelete('set null');
            $table->string('commenter');
            $table->string('email')->nullable();
            $table->text('text');
            $table->float('x')->nullable();
            $table->float('y')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_comments');
    }
};
