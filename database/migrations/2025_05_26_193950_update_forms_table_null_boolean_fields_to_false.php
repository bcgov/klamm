<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Form;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Form::whereNull('icm_generated')->update(['icm_generated' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Form::where('icm_generated', false)->update(['icm_generated' => null]);
    }
};
