<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymous_siebel_stagings', function (Blueprint $table) {
            if (! Schema::hasColumn('anonymous_siebel_stagings', 'qualfield')) {
                $table->string('qualfield', 512)->nullable()->after('column_name');
            }
            if (! Schema::hasColumn('anonymous_siebel_stagings', 'anon_rule')) {
                $table->string('anon_rule', 255)->nullable()->after('column_name');
            }
            if (! Schema::hasColumn('anonymous_siebel_stagings', 'anon_note')) {
                $table->text('anon_note')->nullable()->after('anon_rule');
            }
            if (! Schema::hasColumn('anonymous_siebel_stagings', 'pr_key')) {
                $table->string('pr_key', 32)->nullable()->after('anon_note');
            }
            if (! Schema::hasColumn('anonymous_siebel_stagings', 'ref_tab_name')) {
                $table->string('ref_tab_name', 255)->nullable()->after('pr_key');
            }
            if (! Schema::hasColumn('anonymous_siebel_stagings', 'num_distinct')) {
                $table->unsignedBigInteger('num_distinct')->nullable()->after('ref_tab_name');
            }
            if (! Schema::hasColumn('anonymous_siebel_stagings', 'num_not_null')) {
                $table->unsignedBigInteger('num_not_null')->nullable()->after('num_distinct');
            }
            if (! Schema::hasColumn('anonymous_siebel_stagings', 'num_nulls')) {
                $table->unsignedBigInteger('num_nulls')->nullable()->after('num_not_null');
            }
            if (! Schema::hasColumn('anonymous_siebel_stagings', 'num_rows')) {
                $table->unsignedBigInteger('num_rows')->nullable()->after('num_nulls');
            }
            if (! Schema::hasColumn('anonymous_siebel_stagings', 'sbl_user_name')) {
                $table->string('sbl_user_name', 255)->nullable()->after('column_comment');
            }
            if (! Schema::hasColumn('anonymous_siebel_stagings', 'sbl_desc_text')) {
                $table->text('sbl_desc_text')->nullable()->after('sbl_user_name');
            }
        });

        Schema::table('anonymous_siebel_columns', function (Blueprint $table) {
            if (! Schema::hasColumn('anonymous_siebel_columns', 'qualfield')) {
                $table->string('qualfield', 512)->nullable()->after('column_name');
            }
            if (! Schema::hasColumn('anonymous_siebel_columns', 'pr_key')) {
                $table->string('pr_key', 32)->nullable()->after('column_id');
            }
            if (! Schema::hasColumn('anonymous_siebel_columns', 'ref_tab_name')) {
                $table->string('ref_tab_name', 255)->nullable()->after('pr_key');
            }
            if (! Schema::hasColumn('anonymous_siebel_columns', 'num_distinct')) {
                $table->unsignedBigInteger('num_distinct')->nullable()->after('ref_tab_name');
            }
            if (! Schema::hasColumn('anonymous_siebel_columns', 'num_not_null')) {
                $table->unsignedBigInteger('num_not_null')->nullable()->after('num_distinct');
            }
            if (! Schema::hasColumn('anonymous_siebel_columns', 'num_nulls')) {
                $table->unsignedBigInteger('num_nulls')->nullable()->after('num_not_null');
            }
            if (! Schema::hasColumn('anonymous_siebel_columns', 'num_rows')) {
                $table->unsignedBigInteger('num_rows')->nullable()->after('num_nulls');
            }
            if (! Schema::hasColumn('anonymous_siebel_columns', 'sbl_user_name')) {
                $table->string('sbl_user_name', 255)->nullable()->after('column_comment');
            }
            if (! Schema::hasColumn('anonymous_siebel_columns', 'sbl_desc_text')) {
                $table->text('sbl_desc_text')->nullable()->after('sbl_user_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('anonymous_siebel_stagings', function (Blueprint $table) {
            foreach (
                [
                    'sbl_desc_text',
                    'sbl_user_name',
                    'num_rows',
                    'num_nulls',
                    'num_not_null',
                    'num_distinct',
                    'ref_tab_name',
                    'pr_key',
                    'anon_note',
                    'anon_rule',
                    'qualfield',
                ] as $column
            ) {
                if (Schema::hasColumn('anonymous_siebel_stagings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('anonymous_siebel_columns', function (Blueprint $table) {
            foreach (
                [
                    'sbl_desc_text',
                    'sbl_user_name',
                    'num_rows',
                    'num_nulls',
                    'num_not_null',
                    'num_distinct',
                    'ref_tab_name',
                    'pr_key',
                    'qualfield',
                ] as $column
            ) {
                if (Schema::hasColumn('anonymous_siebel_columns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
