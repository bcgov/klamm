<?php

use App\Jobs\SyncAnonymousSiebelColumnsJob;
use App\Models\Anonymizer\AnonymousUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

describe('Anonymous Siebel Columns Sync Job', function () {

    test('sync job ingests truncated relationships csv and records dependencies', function () {
        // Arrange: load a fixture upload into the fake storage disk.
        Storage::fake('local');

        $relativePath = 'anonymous/relationships-columns-truncated.csv';
        $sourcePath = base_path('scripts/ScriptTemplates/relationships-columns-truncated.csv');
        expect(is_file($sourcePath))->toBeTrue();
        Storage::disk('local')->put($relativePath, file_get_contents($sourcePath));

        // Act: create the upload model and trigger the sync job inline.
        $upload = AnonymousUpload::create([
            'file_disk' => 'local',
            'file_name' => 'relationships-columns-truncated.csv',
            'path' => $relativePath,
            'original_name' => 'relationships-columns-truncated.csv',
            'status' => 'queued',
        ]);

        (new SyncAnonymousSiebelColumnsJob($upload->id))->handle();

        // Assert: verify status transitions and mutation counters.
        $upload->refresh();

        expect($upload->status)->toBe('completed');
        expect($upload->inserted)->toBe(20);
        expect($upload->updated)->toBe(0);
        expect($upload->deleted)->toBe(0);

        expect(DB::table('anonymous_siebel_columns')->count())->toBe(20);
        expect(DB::table('anonymous_siebel_databases')->where('database_name', 'SBLDEV')->exists())->toBeTrue();

        // Assert: confirm column record contains parsed relationships.
        $acctColumn = DB::table('anonymous_siebel_columns as c')
            ->join('anonymous_siebel_tables as t', 'c.table_id', '=', 't.id')
            ->join('anonymous_siebel_schemas as s', 't.schema_id', '=', 's.id')
            ->select('c.id', 'c.related_columns')
            ->where('s.schema_name', 'DEVPS_BIPLATFORM')
            ->where('t.table_name', 'S_NQ_ACCT')
            ->where('c.column_name', 'ID')
            ->first();

        expect($acctColumn)->not->toBeNull();

        $relationships = json_decode($acctColumn->related_columns, true);
        expect($relationships)->toBeArray();

        $hasLogicalQueryRelation = collect($relationships)->contains(
            fn(array $relation) => ($relation['schema'] ?? null) === 'DEVPS_BIPLATFORM'
                && ($relation['table'] ?? null) === 'S_NQ_DB_ACCT'
                && ($relation['column'] ?? null) === 'LOGICAL_QUERY_ID'
        );

        expect($hasLogicalQueryRelation)->toBeTrue();

        $logicalQueryColumnId = DB::table('anonymous_siebel_columns as c')
            ->join('anonymous_siebel_tables as t', 'c.table_id', '=', 't.id')
            ->join('anonymous_siebel_schemas as s', 't.schema_id', '=', 's.id')
            ->where('s.schema_name', 'DEVPS_BIPLATFORM')
            ->where('t.table_name', 'S_NQ_DB_ACCT')
            ->where('c.column_name', 'LOGICAL_QUERY_ID')
            ->value('c.id');

        expect($logicalQueryColumnId)->not->toBeNull();

        // Assert: dependency table includes the outbound edge from ID to LOGICAL_QUERY_ID.
        $dependencyExists = DB::table('anonymous_siebel_column_dependencies')
            ->where('parent_field_id', $acctColumn->id)
            ->where('child_field_id', $logicalQueryColumnId)
            ->exists();

        expect($dependencyExists)->toBeTrue();
    });
});
