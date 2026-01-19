<?php

namespace Database\Seeders;

use App\Models\Anonymizer\AnonymizationMethods;
use Illuminate\Database\Seeder;

class AnonymizationSqlOnlyMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'name' => 'SQL Deterministic Siebel ROW_ID (SHA-256)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Deterministically remaps Siebel ROW_ID values using Oracle SQL only (STANDARD_HASH).',
                'what_it_does' => 'Replaces ROW_ID with a stable, job-seeded surrogate so related foreign keys can be remapped consistently.',
                'how_it_works' => 'Uses STANDARD_HASH(job_seed + ROW_ID) and truncates to the column length.',
                'sql_block' => <<<SQL
-- SQL-only deterministic ROW_ID remap (no package dependency)
update {{TABLE}} tgt
    set {{COLUMN}} = substr(
        lower(rawtohex(standard_hash(
            {{JOB_SEED_LITERAL}} || '|ROW_ID|' || tgt.{{COLUMN}},
            'SHA256'
        ))),
        1,
        {{COLUMN_MAX_LEN_EXPR}}
    )
 where {{COLUMN}} is not null;
SQL,
                'emits_seed' => true,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Emits a deterministic ROW_ID seed so related FKs can map via seed maps.',
            ],
            [
                'name' => 'SQL Deterministic First Name (SHA-256)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Deterministically replaces first names using Oracle SQL only (STANDARD_HASH).',
                'what_it_does' => 'Swaps the source first name for a stable synthetic token that is not reversible.',
                'how_it_works' => 'Uses STANDARD_HASH(job_seed + seed + original) and truncates the hex digest to a readable surrogate.',
                'sql_block' => <<<SQL
-- SQL-only deterministic first-name surrogate (no package dependency)
update {{TABLE}} tgt
    set {{COLUMN}} = substr(
        'FN_' || substr(
            lower(rawtohex(standard_hash(
                {{JOB_SEED_LITERAL}} || '|FN|' || to_char({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
                'SHA256'
            ))),
            1,
            12
        ),
        1,
        {{COLUMN_MAX_LEN_EXPR}}
    )
 where {{COLUMN}} is not null;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Consumes {{SEED_EXPR}} (typically ROW_ID or INTEGRATION_ID).',
            ],
            [
                'name' => 'SQL Deterministic Last Name (SHA-256)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Deterministically replaces last names using Oracle SQL only (STANDARD_HASH).',
                'what_it_does' => 'Swaps the source last name for a stable synthetic token that is not reversible.',
                'how_it_works' => 'Uses STANDARD_HASH(job_seed + seed + original) and truncates the hex digest to a readable surrogate.',
                'sql_block' => <<<SQL
-- SQL-only deterministic last-name surrogate (no package dependency)
update {{TABLE}} tgt
    set {{COLUMN}} = substr(
        'LN_' || substr(
            lower(rawtohex(standard_hash(
                {{JOB_SEED_LITERAL}} || '|LN|' || to_char({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
                'SHA256'
            ))),
            1,
            12
        ),
        1,
        {{COLUMN_MAX_LEN_EXPR}}
    )
 where {{COLUMN}} is not null;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Consumes {{SEED_EXPR}} (typically ROW_ID or INTEGRATION_ID).',
            ],
            [
                'name' => 'SQL Deterministic Email (SHA-256)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Deterministically generates synthetic emails using Oracle SQL only (STANDARD_HASH).',
                'what_it_does' => 'Replaces email addresses with stable synthetic ones (hash-based local part + fixed safe domain).',
                'how_it_works' => 'Uses STANDARD_HASH(job_seed + seed + original) to generate a stable local-part.',
                'sql_block' => <<<SQL
-- SQL-only deterministic email surrogate (no package dependency)
update {{TABLE}} tgt
    set {{COLUMN}} = substr(
        lower(
            'user_' || substr(
                lower(rawtohex(standard_hash(
                    {{JOB_SEED_LITERAL}} || '|EMAIL|' || to_char({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
                    'SHA256'
                ))),
                1,
                12
            ) || '@example.org'
        ),
        1,
        {{COLUMN_MAX_LEN_EXPR}}
    )
 where {{COLUMN}} is not null;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Consumes {{SEED_EXPR}} (typically ROW_ID or INTEGRATION_ID).',
            ],
            [
                'name' => 'SQL Date Shift (±365 days)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Deterministically shifts dates using Oracle SQL only (STANDARD_HASH).',
                'what_it_does' => 'Moves dates by a stable pseudo-random offset within ±365 days.',
                'how_it_works' => 'Hashes (job_seed + seed + date) and maps the digest to a bounded integer delta.',
                'sql_block' => <<<SQL
-- SQL-only deterministic date shift (±365 days)
update {{TABLE}} tgt
    set {{COLUMN}} = tgt.{{COLUMN}} + (
        mod(
            to_number(
                substr(
                    lower(rawtohex(standard_hash(
                        {{JOB_SEED_LITERAL}} || '|DATE|' || to_char({{SEED_EXPR}}) || '|' || to_char(tgt.{{COLUMN}}, 'YYYYMMDD'),
                        'SHA256'
                    ))),
                    1,
                    8
                ),
                'xxxxxxxx'
            ),
            731
        ) - 365
    )
 where {{COLUMN}} is not null;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Consumes {{SEED_EXPR}} (typically ROW_ID or INTEGRATION_ID).',
            ],
            [
                'name' => 'SQL Seed Map Lookup (FK)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Remaps foreign-key-like columns by looking up the provider’s old→new mapping in a generated seed map table.',
                'what_it_does' => 'Keeps cross-table relationships intact when the referenced key column is remapped.',
                'how_it_works' => 'Uses {{SEED_MAP_LOOKUP}} (a subquery against the seed map table) and falls back to the original value when no mapping exists.',
                'sql_block' => <<<SQL
-- SQL-only FK remap via seed-map lookup
update {{TABLE}} tgt
    set {{COLUMN}} = substr(
        nvl({{SEED_MAP_LOOKUP}}, tgt.{{COLUMN}}),
        1,
        {{COLUMN_MAX_LEN_EXPR}}
    )
 where {{COLUMN}} is not null;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Requires a parent seed provider so a seed map table can be generated and referenced via {{SEED_MAP_LOOKUP}}.',
            ],
            [
                'name' => 'Seed Provider (No-Op)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Declares a column as a seed provider with no masking update.',
                'what_it_does' => 'Marks a column as a seed provider for downstream deterministic methods.',
                'how_it_works' => 'No SQL is executed for the column; it exists only to supply {{SEED_EXPR}} and/or seed maps.',
                'sql_block' => "-- Seed provider only (no-op)\n-- No SQL changes required for this column.\n",
                'emits_seed' => true,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Use for provider columns like ROW_ID when they should participate in seed maps but not be updated.',
            ],
        ];

        foreach ($methods as $payload) {
            $method = AnonymizationMethods::withTrashed()->updateOrCreate(
                ['name' => $payload['name']],
                $payload
            );

            if ($method->trashed()) {
                $method->restore();
            }
        }
    }
}
