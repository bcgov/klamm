<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_methods', function (Blueprint $table) {
            if (! Schema::hasColumn('anonymization_methods', 'categories')) {
                $table->json('categories')->nullable()->after('category');
            }
        });

        // Backfill categories from legacy single category.
        DB::table('anonymization_methods')
            ->select(['id', 'category', 'categories'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    if ($row->categories !== null) {
                        continue;
                    }

                    if (! is_string($row->category) || trim($row->category) === '') {
                        continue;
                    }

                    DB::table('anonymization_methods')
                        ->where('id', $row->id)
                        ->update([
                            'categories' => json_encode([trim($row->category)]),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('anonymization_methods', function (Blueprint $table) {
            if (Schema::hasColumn('anonymization_methods', 'categories')) {
                $table->dropColumn('categories');
            }
        });
    }
};
