<?php

namespace App\Services\Anonymizer\Concerns;

use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymousSiebelTable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait BuildsDoubleSeededDeterministicOracleScripts
{
    public function buildDoubleSeededDeterministicFromColumns(Collection $columns, AnonymizationJobs $job): string
    {
        if ($columns->isEmpty()) {
            return '';
        }

        if (method_exists($columns, 'loadMissing')) {
            $columns->loadMissing([
                'anonymizationMethods.packages',
                'table.schema.database',
                'parentColumns.table.schema.database',
            ]);
        }

        $ordered = $this->topologicallySortColumns($columns);
        if ($ordered->isEmpty()) {
            return '';
        }

        $rewriteContext = $this->buildJobTableRewriteContext($ordered, $job);
        $targetSchema = (string) ($rewriteContext['target_schema'] ?? '');
        if ($targetSchema === '') {
            return '-- No SQL generated: unable to resolve job target schema.';
        }

        $seedPrefix = trim((string) ($job->seed_store_prefix ?? ''));
        if ($seedPrefix === '') {
            $seedPrefix = 'JOB';
        }
        $seedPrefix = $this->oracleIdentifier(Str::upper($seedPrefix));

        $seedStoreSchema = trim((string) ($job->seed_store_schema ?? ''));
        if ($seedStoreSchema === '') {
            $seedStoreSchema = $targetSchema;
        }

        $jobKeyName = $this->oracleIdentifier(Str::upper((string) $job->name));
        $jobSeedLiteral = $this->oracleStringLiteral($job->job_seed);

        $jobSeedTable = $seedStoreSchema . '.' . $this->oracleIdentifier($seedPrefix . '_JOB_SEED');
        $integrationSeedMap = $seedStoreSchema . '.' . $this->oracleIdentifier($seedPrefix . '_SEED_INTEGRATION_ID');

        // Map selected source tables to stable Demo_* working copies (no random suffixes).
        $tables = $this->collectTablesForDoubleSeededJob($ordered);
        $tableMappings = $this->buildStableDemoTableMappings($tables, $targetSchema, $seedPrefix);

        $lines = [];

        $lines[] = $this->commentDivider('=');
        $lines[] = '-- Anonymization Job: ' . $job->name;
        $lines[] = '-- Generated: ' . now()->toDateString();
        $lines[] = '-- Job Type: Deterministic (Double-Seeded)';
        $lines[] = '-- Target DB: Oracle';
        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        $lines[] = $this->commentDivider('-');
        $lines[] = '-- EXECUTION CONTEXT';
        $lines[] = $this->commentDivider('-');
        $lines[] = 'ALTER SESSION SET CURRENT_SCHEMA = ' . $targetSchema . ';';
        $lines[] = '';

        $lines[] = $this->commentDivider('-');
        $lines[] = '-- GLOBAL JOB SEED';
        $lines[] = '-- Controls run-to-run reproducibility';
        $lines[] = $this->commentDivider('-');
        $lines[] = 'BEGIN';
        $lines[] = "  EXECUTE IMMEDIATE '";
        $lines[] = '    CREATE TABLE ' . $jobSeedTable . ' (';
        $lines[] = '      job_name VARCHAR2(64) PRIMARY KEY,';
        $lines[] = '      job_seed VARCHAR2(128) NOT NULL';
        $lines[] = "    )'";
        $lines[] = ';';
        $lines[] = 'EXCEPTION';
        $lines[] = '  WHEN OTHERS THEN';
        $lines[] = '    IF SQLCODE != -955 THEN RAISE; END IF;';
        $lines[] = 'END;';
        $lines[] = '/';
        $lines[] = '';

        $lines[] = 'MERGE INTO ' . $jobSeedTable . ' t';
        $lines[] = 'USING (';
        $lines[] = '  SELECT';
        $lines[] = '    ' . $this->oracleStringLiteral($jobKeyName) . ' job_name,';
        $lines[] = '    ' . $jobSeedLiteral . '      job_seed';
        $lines[] = '  FROM dual';
        $lines[] = ') s';
        $lines[] = 'ON (t.job_name = s.job_name)';
        $lines[] = 'WHEN MATCHED THEN';
        $lines[] = '  UPDATE SET t.job_seed = s.job_seed';
        $lines[] = 'WHEN NOT MATCHED THEN';
        $lines[] = '  INSERT (job_name, job_seed)';
        $lines[] = '  VALUES (s.job_name, s.job_seed);';
        $lines[] = '';
        $lines[] = 'COMMIT;';
        $lines[] = '';

        $packages = $this->collectPackagesFromColumns($ordered);

        if ($packages->isNotEmpty()) {
            $lines[] = $this->commentDivider('-');
            $lines[] = '-- DETERMINISTIC HELPER PACKAGE';
            $lines[] = '-- No DBMS_RANDOM, hash-based only';
            $lines[] = $this->commentDivider('-');

            foreach ($packages as $package) {
                foreach ($package->compiledSqlBlocks() as $block) {
                    $rewritten = $this->rewritePackageSqlBlock((string) $block, $rewriteContext);
                    $rewritten = $this->applyJobPlaceholdersToSql($rewritten, [
                        '{{JOB_NAME}}' => $this->oracleStringLiteral($jobKeyName),
                        '{{JOB_SEED_LITERAL}}' => $jobSeedLiteral,
                        '{{JOB_SEED_TABLE}}' => $jobSeedTable,
                        '{{TARGET_SCHEMA}}' => $targetSchema,
                        '{{SEED_STORE_SCHEMA}}' => $seedStoreSchema,
                        '{{SEED_PREFIX}}' => $seedPrefix,
                    ]);

                    $lines = array_merge($lines, preg_split('/\R/', trim($rewritten)) ?: []);
                    $lines[] = '';
                }
            }
        }

        $lines[] = $this->commentDivider('-');
        $lines[] = '-- WORKING COPY CREATION';
        $lines[] = '-- Parent table: S_CONTACT';
        $lines[] = $this->commentDivider('-');

        foreach ($tableMappings as $mapping) {
            $lines = array_merge($lines, $this->renderStableCloneStatements($mapping['source_qualified'], $mapping['target_qualified']));

            // Add ORIGINAL_INTEGRATION_ID to the parent clone so we can seed deterministically.
            if (($mapping['source_table'] ?? '') === 'S_CONTACT') {
                $lines[] = 'ALTER TABLE ' . $mapping['target_qualified'];
                $lines[] = 'ADD ORIGINAL_INTEGRATION_ID VARCHAR2(255);';
                $lines[] = '';
                $lines[] = 'UPDATE ' . $mapping['target_qualified'];
                $lines[] = 'SET ORIGINAL_INTEGRATION_ID = INTEGRATION_ID;';
                $lines[] = '';
                $lines[] = 'COMMIT;';
                $lines[] = '';
            }
        }

        $lines[] = $this->commentDivider('-');
        $lines[] = '-- CROSS-TABLE SEED MAP';
        $lines[] = '-- Used by child tables (FK preservation)';
        $lines[] = $this->commentDivider('-');

        // Create/refresh the INTEGRATION_ID seed map from ORIGINAL_INTEGRATION_ID (FK preservation).
        $parentContactTarget = $this->findTargetQualifiedForSourceTable($tableMappings, 'S_CONTACT');
        if ($parentContactTarget !== null) {
            $lines[] = 'BEGIN';
            $lines[] = "  EXECUTE IMMEDIATE 'CREATE TABLE {$integrationSeedMap} (old_value VARCHAR2(255) PRIMARY KEY, new_value VARCHAR2(255))'";
            $lines[] = ';';
            $lines[] = 'EXCEPTION';
            $lines[] = '  WHEN OTHERS THEN';
            $lines[] = '    IF SQLCODE != -955 THEN RAISE; END IF;';
            $lines[] = 'END;';
            $lines[] = '/';
            $lines[] = '';

            $lines[] = 'DECLARE';
            $lines[] = '  v_job_seed varchar2(128);';
            $lines[] = 'BEGIN';
            $lines[] = '  SELECT job_seed';
            $lines[] = '  INTO v_job_seed';
            $lines[] = '  FROM ' . $jobSeedTable;
            $lines[] = '  WHERE job_name = ' . $this->oracleStringLiteral($jobKeyName) . ';';
            $lines[] = '';

            $lines[] = '  MERGE INTO ' . $integrationSeedMap . ' sm';
            $lines[] = '  USING (';
            $lines[] = '    SELECT DISTINCT';
            $lines[] = '      tgt.ORIGINAL_INTEGRATION_ID AS old_value,';
            $lines[] = "      '9-' || substr(lower(rawtohex(standard_hash(v_job_seed || '|INTID|' || tgt.ORIGINAL_INTEGRATION_ID, 'SHA256'))), 1, 16) AS new_value";
            $lines[] = '    FROM ' . $parentContactTarget . ' tgt';
            $lines[] = '  ) src';
            $lines[] = '  ON (sm.old_value = src.old_value)';
            $lines[] = '  WHEN MATCHED THEN';
            $lines[] = '    UPDATE SET sm.new_value = src.new_value';
            $lines[] = '  WHEN NOT MATCHED THEN';
            $lines[] = '    INSERT (old_value, new_value) VALUES (src.old_value, src.new_value);';
            $lines[] = 'END;';
            $lines[] = '/';
            $lines[] = 'COMMIT;';
            $lines[] = '';
        }

        $lines[] = $this->commentDivider('-');
        $lines[] = '-- MASK DEPENDENT COLUMNS FIRST';
        $lines[] = $this->commentDivider('-');

        if ($parentContactTarget !== null) {
            $lines[] = 'DECLARE';
            $lines[] = '  v_job_seed varchar2(128);';
            $lines[] = 'BEGIN';
            $lines[] = '  SELECT job_seed';
            $lines[] = '  INTO v_job_seed';
            $lines[] = '  FROM ' . $jobSeedTable;
            $lines[] = '  WHERE job_name = ' . $this->oracleStringLiteral($jobKeyName) . ';';
            $lines[] = '';

            $lines[] = '  UPDATE ' . $parentContactTarget . ' t';
            $lines[] = '  SET';
            $lines[] = "    SIN = lpad(mod(to_number(substr(lower(rawtohex(standard_hash(v_job_seed || '|SIN|' || t.ORIGINAL_INTEGRATION_ID || '|' || nvl(t.SIN, ''), 'SHA256'))), 1, 8), 'xxxxxxxx'), 1000000000), 9, '0'),";
            $lines[] = "    PHN = lpad(mod(to_number(substr(lower(rawtohex(standard_hash(v_job_seed || '|PHN|' || t.ORIGINAL_INTEGRATION_ID || '|' || nvl(t.PHN, ''), 'SHA256'))), 1, 8), 'xxxxxxxx'), 10000000000), 10, '0'),";
            $lines[] = "    CELL_PH_NUM = lpad(mod(to_number(substr(lower(rawtohex(standard_hash(v_job_seed || '|PHONE|' || t.ORIGINAL_INTEGRATION_ID || '|' || nvl(t.CELL_PH_NUM, ''), 'SHA256'))), 1, 8), 'xxxxxxxx'), 10000000000), 10, '0');";
            $lines[] = 'END;';
            $lines[] = '/';
            $lines[] = 'COMMIT;';
            $lines[] = '';
        }

        $lines[] = $this->commentDivider('-');
        $lines[] = '-- MASK INTEGRATION ID LAST (PARENT KEY)';
        $lines[] = $this->commentDivider('-');

        if ($parentContactTarget !== null) {
            $lines[] = 'UPDATE ' . $parentContactTarget . ' t';
            $lines[] = 'SET INTEGRATION_ID =';
            $lines[] = '    (SELECT sm.new_value';
            $lines[] = '       FROM ' . $integrationSeedMap . ' sm';
            $lines[] = '      WHERE sm.old_value = t.ORIGINAL_INTEGRATION_ID);';
            $lines[] = '';
            $lines[] = 'COMMIT;';
            $lines[] = '';
        }

        $lines[] = $this->commentDivider('-');
        $lines[] = '-- CHILD TABLE EXAMPLE (CROSS-TABLE JOIN)';
        $lines[] = '-- Example: S_CONTACT_XM (FK → INTEGRATION_ID)';
        $lines[] = $this->commentDivider('-');

        $childTarget = $this->findTargetQualifiedForSourceTable($tableMappings, 'S_CONTACT_XM');
        if ($childTarget !== null) {
            $lines[] = 'UPDATE ' . $childTarget . ' c';
            $lines[] = 'SET c.INTEGRATION_ID =';
            $lines[] = '  (SELECT sm.new_value';
            $lines[] = '     FROM ' . $integrationSeedMap . ' sm';
            $lines[] = '    WHERE sm.old_value = c.INTEGRATION_ID);';
            $lines[] = '';
            $lines[] = 'COMMIT;';
            $lines[] = '';
        }

        $lines[] = $this->commentDivider('-');
        $lines[] = '-- END OF JOB';
        $lines[] = $this->commentDivider('-');
        $lines[] = 'prompt ' . $seedPrefix . ' Deterministic Contact Masking complete';

        return trim(implode(PHP_EOL, $lines));
    }

    protected function collectTablesForDoubleSeededJob(Collection $columns): Collection
    {
        $tables = collect();

        foreach ($columns as $column) {
            $table = $column->getRelationValue('table');
            if ($table) {
                $tables->push($table);
            }

            $parents = $column->getRelationValue('parentColumns') ?? collect();
            foreach ($parents as $parent) {
                $parentTable = $parent->getRelationValue('table');
                if ($parentTable) {
                    $tables->push($parentTable);
                }
            }
        }

        return $tables
            ->filter()
            ->unique(fn($t) => (int) $t->getKey())
            ->values();
    }

    /**
     * @param Collection<int, AnonymousSiebelTable> $tables
     */
    protected function buildStableDemoTableMappings(Collection $tables, string $targetSchema, string $seedPrefix): array
    {
        $mappings = [];

        foreach ($tables as $table) {
            $schema = $table->getRelationValue('schema');
            $sourceSchema = $schema?->schema_name;
            $sourceTable = $table->table_name;

            if (! $sourceSchema || ! $sourceTable) {
                continue;
            }

            $sourceQualified = $sourceSchema . '.' . $sourceTable;
            $demoSuffix = $this->stableDemoSuffixForSourceTable($sourceTable);
            $targetTable = $this->oracleIdentifier('Demo_' . $seedPrefix . '_' . $demoSuffix);
            $targetQualified = $targetSchema . '.' . $targetTable;

            $mappings[] = [
                'source_schema' => $sourceSchema,
                'source_table' => $sourceTable,
                'source_qualified' => $sourceQualified,
                'target_schema' => $targetSchema,
                'target_table' => $targetTable,
                'target_qualified' => $targetQualified,
            ];
        }

        usort($mappings, fn($a, $b) => strcmp((string) ($a['source_table'] ?? ''), (string) ($b['source_table'] ?? '')));

        return $mappings;
    }

    protected function stableDemoSuffixForSourceTable(string $sourceTable): string
    {
        $normalized = Str::upper(trim($sourceTable));
        if (str_starts_with($normalized, 'S_')) {
            $normalized = substr($normalized, 2);
        }

        $parts = array_values(array_filter(explode('_', $normalized), fn($p) => $p !== ''));
        if ($parts === []) {
            return 'Table';
        }

        $rendered = [];
        foreach ($parts as $part) {
            if ($part === 'XM' || $part === 'XREF') {
                $rendered[] = $part;
                continue;
            }

            $rendered[] = Str::ucfirst(Str::lower($part));
        }

        return implode('_', $rendered);
    }

    protected function renderStableCloneStatements(string $qualifiedSource, string $qualifiedTarget): array
    {
        return [
            'BEGIN',
            "  EXECUTE IMMEDIATE 'DROP TABLE {$qualifiedTarget} PURGE';",
            'EXCEPTION',
            '  WHEN OTHERS THEN',
            '    IF SQLCODE != -942 THEN RAISE; END IF;',
            'END;',
            '/',
            '',
            'CREATE TABLE ' . $qualifiedTarget . ' AS',
            'SELECT * FROM ' . $qualifiedSource . ';',
            '',
        ];
    }

    protected function findTargetQualifiedForSourceTable(array $mappings, string $sourceTable): ?string
    {
        foreach ($mappings as $mapping) {
            if (($mapping['source_table'] ?? null) === $sourceTable) {
                return $mapping['target_qualified'] ?? null;
            }
        }

        return null;
    }

    protected function applyJobPlaceholdersToSql(string $sql, array $replacements): string
    {
        if ($replacements === []) {
            return $sql;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $sql);
    }
}
