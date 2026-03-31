<?php

namespace Database\Seeders\Anonymization;

use App\Models\Anonymizer\AnonymizationMethods;
use App\Models\Anonymizer\AnonymizationRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a curated set of anonymization methods and rules, then wires each rule
 * to its default method via the anonymization_rule_methods pivot.
 *
 * This seeder is intentionally self-contained: it includes only the methods
 * that are actually referenced by the rule catalog, dramatically reducing the
 * total method count compared to the comprehensive/Faker/SQL-only seeders.
 *
 * Usage:
 *   sail artisan db:seed --class=AnonymizationRuleMethodSeeder
 */
class AnonymizationRuleMethodSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Seed curated methods ─────────────────────────────────────
        $methods = $this->curatedMethods();
        $methodIdsByName = [];

        foreach ($methods as $payload) {
            $method = AnonymizationMethods::withTrashed()->updateOrCreate(
                ['name' => $payload['name']],
                $payload
            );

            if ($method->trashed()) {
                $method->restore();
            }

            $methodIdsByName[$method->name] = $method->id;
        }

        $this->command?->info('Seeded ' . count($methods) . ' curated anonymization methods.');

        // ── 2. Seed rules & wire default methods ────────────────────────
        $rules = $this->ruleDefinitions();
        $rulesCreated = 0;
        $associations = 0;

        foreach ($rules as $ruleDef) {
            $rule = AnonymizationRule::withTrashed()->updateOrCreate(
                ['name' => $ruleDef['name']],
                ['description' => $ruleDef['description'] ?? null]
            );

            if ($rule->trashed()) {
                $rule->restore();
            }

            $rulesCreated++;

            $methodName = $ruleDef['default_method'] ?? null;

            if ($methodName === null) {
                continue;
            }

            $methodId = $methodIdsByName[$methodName] ?? null;

            if ($methodId === null) {
                $this->command?->warn("Rule '{$ruleDef['name']}': method '{$methodName}' not found; skipping association.");

                continue;
            }

            // Upsert into the rule-methods pivot (one default per rule)
            $existingPivot = DB::table('anonymization_rule_methods')
                ->where('rule_id', $rule->id)
                ->where('method_id', $methodId)
                ->first();

            if ($existingPivot) {
                DB::table('anonymization_rule_methods')
                    ->where('rule_id', $rule->id)
                    ->where('method_id', $methodId)
                    ->update([
                        'is_default' => true,
                        'strategy' => null,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('anonymization_rule_methods')->insert([
                    'rule_id' => $rule->id,
                    'method_id' => $methodId,
                    'is_default' => true,
                    'strategy' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $associations++;
        }

        $this->command?->info("Seeded {$rulesCreated} anonymization rules ({$associations} with method associations).");
    }

    // =====================================================================
    //  CURATED METHOD DEFINITIONS
    // =====================================================================

    protected function curatedMethods(): array
    {
        return array_merge(
            $this->fakerLookupMethods(),
            $this->dateTimeMethods(),
            $this->numericMethods(),
            $this->identifierMethods(),
            $this->shuffleAndRedactMethods(),
            $this->siebelKeyMethods(),
        );
    }

    // ─── Faker lookup (deterministic) ───────────────────────────────

    protected function fakerLookupMethods(): array
    {
        return [
            [
                'name' => 'Faker First Name (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces first names with synthetic values from a pre-generated lookup table.',
                'what_it_does' => 'Maps original first names to realistic synthetic first names deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ Faker-generated first names.',
                'sql_block' => <<<'SQL'
-- Deterministic first name lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_FIRST_NAMES.GET_FIRST_NAMES(
       {{JOB_SEED_LITERAL}} || '|FN|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Requires seed expression. Produces consistent results for same seed.',
            ],
            [
                'name' => 'Faker Last Name (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces last names with synthetic values from a pre-generated lookup table.',
                'what_it_does' => 'Maps original last names to realistic synthetic last names deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ Faker-generated last names.',
                'sql_block' => <<<'SQL'
-- Deterministic last name lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_LAST_NAMES.GET_LAST_NAMES(
       {{JOB_SEED_LITERAL}} || '|LN|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Requires seed expression. Produces consistent results for same seed.',
            ],
            [
                'name' => 'Faker Full Name (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces full names with synthetic values from a pre-generated lookup table.',
                'what_it_does' => 'Maps original full names to realistic synthetic full names deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ Faker-generated full names.',
                'sql_block' => <<<'SQL'
-- Deterministic full name lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_FULL_NAMES.GET_FULL_NAMES(
       {{JOB_SEED_LITERAL}} || '|NAME|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Requires seed expression. Produces consistent results for same seed.',
            ],
            [
                'name' => 'Faker Email (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces email addresses with synthetic values using safe domains.',
                'what_it_does' => 'Maps original emails to realistic synthetic emails deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ Faker-generated safe emails.',
                'sql_block' => <<<'SQL'
-- Deterministic email lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_EMAILS.GET_EMAILS(
       {{JOB_SEED_LITERAL}} || '|EMAIL|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'All generated emails use @example.com/org/net domains.',
            ],
            [
                'name' => 'Faker Phone Number (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces phone numbers with synthetic values using reserved exchanges.',
                'what_it_does' => 'Maps original phones to realistic synthetic phone numbers deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ synthetic phone numbers.',
                'sql_block' => <<<'SQL'
-- Deterministic phone lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_PHONES.GET_PHONES(
       {{JOB_SEED_LITERAL}} || '|PHONE|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Uses 555 and toll-free exchanges. Various formats supported.',
            ],
            [
                'name' => 'Faker Street Address (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces street addresses with synthetic values.',
                'what_it_does' => 'Maps original addresses to realistic synthetic addresses deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ Faker-generated addresses.',
                'sql_block' => <<<'SQL'
-- Deterministic address lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_ADDRESSES.GET_ADDRESSES(
       {{JOB_SEED_LITERAL}} || '|ADDR|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Requires seed expression. Max length 200 chars.',
            ],
            [
                'name' => 'Faker City (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces city names with synthetic values.',
                'what_it_does' => 'Maps original cities to realistic synthetic city names deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 5,000+ Faker-generated city names.',
                'sql_block' => <<<'SQL'
-- Deterministic city lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_CITIES.GET_CITIES(
       {{JOB_SEED_LITERAL}} || '|CITY|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Max length 50 chars.',
            ],
            [
                'name' => 'Faker Postal Code (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces postal/ZIP codes with synthetic values.',
                'what_it_does' => 'Maps original postal codes to synthetic US ZIP or Canadian postal codes.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ synthetic postal codes.',
                'sql_block' => <<<'SQL'
-- Deterministic postal code lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_POSTAL_CODES.GET_POSTAL_CODES(
       {{JOB_SEED_LITERAL}} || '|ZIP|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Mix of US ZIP (5-digit) and Canadian postal codes.',
            ],
            [
                'name' => 'Faker Company Name (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces company/organization names with synthetic values.',
                'what_it_does' => 'Maps original company names to realistic synthetic company names.',
                'how_it_works' => 'Uses a seed-based hash to select from 5,000+ Faker-generated company names.',
                'sql_block' => <<<'SQL'
-- Deterministic company name lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_COMPANIES.GET_COMPANIES(
       {{JOB_SEED_LITERAL}} || '|ORG|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Max length 100 chars.',
            ],
            [
                'name' => 'Faker sin Surrogate (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces SIN values with non-valid format-preserving surrogates.',
                'what_it_does' => 'Maps original SIN values to synthetic SIN-format strings that are not valid.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ invalid-but-formatted SIN surrogates.',
                'sql_block' => <<<'SQL'
-- Deterministic SIN surrogate lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_SIN.GET_SIN(
       {{JOB_SEED_LITERAL}} || '|SIN|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Uses invalid SIN area codes (900-999). Not valid for verification.',
            ],
            [
                'name' => 'Faker Account Number (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces account/reference numbers with synthetic values.',
                'what_it_does' => 'Maps original account numbers to synthetic account number patterns.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ synthetic account numbers.',
                'sql_block' => <<<'SQL'
-- Deterministic account number lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_ACCOUNTS.GET_ACCOUNTS(
       {{JOB_SEED_LITERAL}} || '|ACCT|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Format: PREFIX-NNNNNNNN',
            ],
            [
                'name' => 'Faker Username (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces usernames/login IDs with synthetic values.',
                'what_it_does' => 'Maps original usernames to realistic synthetic usernames.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ Faker-generated usernames.',
                'sql_block' => <<<'SQL'
-- Deterministic username lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_USERNAMES.GET_USERNAMES(
       {{JOB_SEED_LITERAL}} || '|USER|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Max length 50 chars.',
            ],
            [
                'name' => 'Faker Comment/Notes (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces free-text comments with lorem ipsum placeholder text.',
                'what_it_does' => 'Maps original comments to generic lorem ipsum text.',
                'how_it_works' => 'Uses a seed-based hash to select from 5,000+ lorem ipsum sentences.',
                'sql_block' => <<<'SQL'
-- Deterministic comment lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_COMMENTS.GET_COMMENTS(
       {{JOB_SEED_LITERAL}} || '|NOTES|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'For CLOB columns, use with explicit max_len or separate CLOB method.',
            ],
        ];
    }

    // ─── Date / time methods ────────────────────────────────────────

    protected function dateTimeMethods(): array
    {
        return [
            [
                'name' => 'Date Shift (±30 days, Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Shifts dates by ±30 days using deterministic hashing.',
                'what_it_does' => 'Adjusts dates by a stable pseudo-random offset within ±30 days.',
                'how_it_works' => 'Uses STANDARD_HASH to generate a consistent day offset.',
                'sql_block' => <<<'SQL'
-- Date shift ±30 days (deterministic)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = tgt.{{COLUMN}} + (
       MOD(
           TO_NUMBER(
               SUBSTR(
                   LOWER(RAWTOHEX(STANDARD_HASH(
                       {{JOB_SEED_LITERAL}} || '|DATE30|' || TO_CHAR({{SEED_EXPR}}) || '|' || TO_CHAR(tgt.{{COLUMN}}, 'YYYYMMDD'),
                       'SHA256'
                   ))),
                   1, 8
               ),
               'xxxxxxxx'
           ),
           61
       ) - 30
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'For DATE columns. Preserves relative ordering within ±30 day variance.',
            ],
            [
                'name' => 'Date Shift (±90 Days, Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING, AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Deterministically shifts dates by up to ±90 days.',
                'what_it_does' => 'Reproducible date perturbation for consistency across runs.',
                'how_it_works' => 'Uses hash to generate consistent offset per record.',
                'sql_block' => <<<'SQL'
-- Deterministic date shift ±90 days
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = {{COLUMN}} + (
       MOD(ORA_HASH({{JOB_SEED_LITERAL}} || TO_CHAR({{SEED_EXPR}}) || TO_CHAR({{COLUMN}}, 'YYYYMMDDHH24MISS')), 181) - 90
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Requires seed. Same seed+value = same offset. Use for birth/death dates.',
            ],
            [
                'name' => 'Timestamp Shift (±7 days, Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Shifts timestamps by ±7 days using deterministic hashing.',
                'what_it_does' => 'Adjusts timestamps by a stable pseudo-random offset.',
                'how_it_works' => 'Uses STANDARD_HASH to generate a consistent time offset.',
                'sql_block' => <<<'SQL'
-- Timestamp shift ±7 days (deterministic)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = tgt.{{COLUMN}} + (
       MOD(
           TO_NUMBER(
               SUBSTR(
                   LOWER(RAWTOHEX(STANDARD_HASH(
                       {{JOB_SEED_LITERAL}} || '|TS7|' || TO_CHAR({{SEED_EXPR}}) || '|' || TO_CHAR(tgt.{{COLUMN}}, 'YYYYMMDDHH24MISS'),
                       'SHA256'
                   ))),
                   1, 8
               ),
               'xxxxxxxx'
           ),
           15
       ) - 7
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'For TIMESTAMP columns. ±7 day variance.',
            ],
        ];
    }

    // ─── Numeric methods ────────────────────────────────────────────

    protected function numericMethods(): array
    {
        return [
            [
                'name' => 'Numeric Perturbation (±10%)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Perturbs numeric values by ±10% using deterministic hashing.',
                'what_it_does' => 'Adjusts numeric values by a stable pseudo-random percentage.',
                'how_it_works' => 'Uses STANDARD_HASH to generate a consistent ±10% adjustment.',
                'sql_block' => <<<'SQL'
-- Numeric perturbation ±10% (deterministic)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = tgt.{{COLUMN}} * (
       0.9 + (
           MOD(
               TO_NUMBER(
                   SUBSTR(
                       LOWER(RAWTOHEX(STANDARD_HASH(
                           {{JOB_SEED_LITERAL}} || '|NUM|' || TO_CHAR({{SEED_EXPR}}) || '|' || TO_CHAR(tgt.{{COLUMN}}),
                           'SHA256'
                       ))),
                       1, 8
                   ),
                   'xxxxxxxx'
               ),
               201
           ) / 1000.0
       )
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'For NUMBER columns. Preserves sign and approximate magnitude.',
            ],
        ];
    }

    // ─── Identifier / format-preserving methods ─────────────────────

    protected function identifierMethods(): array
    {
        return [
            [
                'name' => 'Format Preserving Alphanumeric (Character Class)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces characters while preserving alpha/numeric/special class positions.',
                'what_it_does' => 'Maintains format structure: A→A, 9→9, special→special.',
                'how_it_works' => 'Translates each character class to deterministic replacement within same class.',
                'sql_block' => <<<'SQL'
-- Format-preserving alphanumeric (maintains character classes)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = TRANSLATE(
       {{COLUMN}},
       'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
       SUBSTR(
           'XQWERTASDFGZXCVBNHJKLYUIOPMXQWERTASDFGZXCVBNHJKLYUIOPM9876543210',
           MOD(ORA_HASH({{JOB_SEED_LITERAL}} || tgt.{{COLUMN}}), 10) + 1,
           62
       ) || '9876543210'
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Preserves string length and character type positions (letter→letter, digit→digit).',
            ],
            [
                'name' => 'Deterministic Text Hash',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces text with deterministic hash-based token.',
                'what_it_does' => 'Generates unique, reproducible token from text content.',
                'how_it_works' => 'Uses SHA-256 hash truncated to readable length.',
                'sql_block' => <<<'SQL'
-- Deterministic text hash
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = 'TXT_' ||
       SUBSTR(LOWER(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'SHA256'))), 1, 16)
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Format: TXT_[16 hex chars]. Same input = same output.',
            ],
            [
                'name' => 'UUID/GUID Replacement',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces UUIDs with deterministic version-4 format values.',
                'what_it_does' => 'Generates synthetic UUIDs that preserve version-4 format.',
                'how_it_works' => 'Uses hash to generate hex values with proper version/variant bits.',
                'sql_block' => <<<'SQL'
-- UUID/GUID deterministic replacement (version-4 format)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = LOWER(
       SUBSTR(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'SHA256')), 1, 8) || '-' ||
       SUBSTR(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'SHA256')), 9, 4) || '-4' ||
       SUBSTR(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'SHA256')), 14, 3) || '-' ||
       CASE MOD(TO_NUMBER(SUBSTR(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'SHA256')), 17, 1), 'X'), 4)
           WHEN 0 THEN '8' WHEN 1 THEN '9' WHEN 2 THEN 'a' ELSE 'b'
       END ||
       SUBSTR(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'SHA256')), 18, 3) || '-' ||
       SUBSTR(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'SHA256')), 21, 12)
   )
 WHERE {{COLUMN}} IS NOT NULL
   AND REGEXP_LIKE({{COLUMN}}, '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Produces valid UUID v4 format (4xxx-[89ab]xxx). Deterministic.',
            ],
            [
                'name' => 'URL Path Anonymization',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Masks URL path segments while preserving domain.',
                'what_it_does' => 'Replaces URL path with /masked-path to hide page access patterns.',
                'how_it_works' => 'Regex extracts and preserves protocol+domain, replaces path.',
                'sql_block' => <<<'SQL'
-- URL path anonymization (preserve domain)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = REGEXP_REPLACE({{COLUMN}}, '(https?://[^/]+)/.*', '\1/masked-path')
 WHERE {{COLUMN}} IS NOT NULL
   AND REGEXP_LIKE({{COLUMN}}, '^https?://');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Keeps protocol and domain. Example: https://example.com/masked-path',
            ],
        ];
    }

    // ─── Shuffle, redact, nullify ───────────────────────────────────

    protected function shuffleAndRedactMethods(): array
    {
        return [
            [
                'name' => 'Intra-Column Value Shuffle',
                'category' => AnonymizationMethods::CATEGORY_SHUFFLE_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_SHUFFLE_MASKING],
                'description' => 'Shuffles values within the same column across rows.',
                'what_it_does' => 'Randomly redistributes existing values, preserving overall distribution.',
                'how_it_works' => 'Uses ROWNUM-based random assignment via self-join.',
                'sql_block' => <<<'SQL'
-- Intra-column shuffle (redistributes values randomly)
MERGE INTO {{TABLE}} tgt
USING (
    SELECT
        ROWID AS rid,
        {{COLUMN}} AS old_val,
        FIRST_VALUE({{COLUMN}}) OVER (ORDER BY DBMS_RANDOM.VALUE) AS new_val
    FROM {{TABLE}}
    WHERE {{COLUMN}} IS NOT NULL
) src
ON (tgt.ROWID = src.rid)
WHEN MATCHED THEN UPDATE SET tgt.{{COLUMN}} = src.new_val;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic. Preserves data distribution. Use DBMS_RANDOM.SEED for reproducibility.',
            ],
            [
                'name' => 'Redact (Fixed Token)',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Replaces a value with a fixed redaction token.',
                'what_it_does' => 'Overwrites sensitive values with a constant (e.g., REDACTED) to eliminate leakage risk.',
                'how_it_works' => 'Simple UPDATE that replaces non-null values with a fixed token.',
                'sql_block' => <<<'SQL'
-- Hard redaction (non-reversible)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = 'REDACTED'
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Use for notes/comments/free-text where format realism is not required.',
            ],
            [
                'name' => 'Nullify (Set NULL)',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Clears a column to NULL (hard suppression).',
                'what_it_does' => 'Eliminates sensitive data completely in columns where NULL is allowed.',
                'how_it_works' => 'Simple UPDATE that sets the column to NULL.',
                'sql_block' => <<<'SQL'
-- Hard suppression (set NULL)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = NULL
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Use only when NULL is allowed and accepted by the application.',
            ],
            [
                'name' => 'Exclude Column (Do Not Copy)',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Marks a column for exclusion from the anonymized dataset. The column should not be copied at all.',
                'what_it_does' => 'Signals that this column (or its entire table) carries no useful data and should be omitted from any export or copy operation.',
                'how_it_works' => 'No SQL is emitted. The job generator should skip columns tagged with this method when building anonymization scripts.',
                'sql_block' => <<<'SQL'
-- Exclude: column flagged for omission (do not copy)
-- No UPDATE required; this column should be excluded from the export.
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Use when the column or table contains no records or is otherwise irrelevant to the anonymized copy.',
            ],
        ];
    }

    // ─── Siebel structural key methods ──────────────────────────────

    protected function siebelKeyMethods(): array
    {
        return [
            [
                'name' => 'SQL Deterministic Siebel ROW_ID (SHA-256)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Deterministically remaps Siebel ROW_ID values using Oracle SQL only.',
                'what_it_does' => 'Replaces ROW_ID with a stable, job-seeded surrogate so related foreign keys can be remapped consistently.',
                'how_it_works' => 'Uses STANDARD_HASH(job_seed + ROW_ID) and truncates to the column length.',
                'sql_block' => <<<'SQL'
-- SQL-only deterministic ROW_ID remap (no package dependency)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = SUBSTR(
       LOWER(RAWTOHEX(STANDARD_HASH(
           {{JOB_SEED_LITERAL}} || '|ROW_ID|' || tgt.{{COLUMN}},
           'SHA256'
       ))),
       1,
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => true,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Emits a deterministic ROW_ID seed so related FKs can map via seed maps.',
            ],
            [
                'name' => 'SQL Seed Map Lookup (FK)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Remaps foreign-key columns by looking up the provider\'s old-to-new mapping in a generated seed map table.',
                'what_it_does' => 'Keeps cross-table relationships intact when the referenced key column is remapped.',
                'how_it_works' => 'Uses {{SEED_MAP_LOOKUP}} (a subquery against the seed map table) and falls back to the original value.',
                'sql_block' => <<<'SQL'
-- SQL-only FK remap via seed-map lookup
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = SUBSTR(
       NVL({{SEED_MAP_LOOKUP}}, tgt.{{COLUMN}}),
       1,
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Requires a parent seed provider so a seed map table can be generated and referenced via {{SEED_MAP_LOOKUP}}.',
            ],
        ];
    }

    // =====================================================================
    //  RULE DEFINITIONS
    // =====================================================================

    /**
     * Returns the full rule catalog.
     *
     * Each entry has:
     *   - name:           Rule name (matches ANON_RULE import column).
     *   - description:    Human-readable description.
     *   - default_method: Name of the default method to associate (null = no method).
     */
    protected function ruleDefinitions(): array
    {
        return [
            // ── Account / reference numbers ─────────────────────────────
            ['name' => 'account_number',       'description' => 'Account or reference number.',                                                                          'default_method' => 'Faker Account Number (Deterministic Lookup)'],
            ['name' => 'agreement_number',     'description' => 'Agreement or contract number.',                                                                         'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'benefit_plan_number',  'description' => 'Benefit plan number.',                                                                                  'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'bus_pass_sr_number',   'description' => 'Bus pass or service request number.',                                                                   'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'case_number',          'description' => 'ICM case number. Anonymization code will probably use common code with other ICM numbers.',              'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'client_id_num',        'description' => 'Client identification number.',                                                                         'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'conflict_id',          'description' => 'Conflict identification number.',                                                                       'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'contact_number',       'description' => 'ICM contact number. Anonymization code will probably use common code with other ICM numbers.',           'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'cpc_file_num',         'description' => 'CPC file number.',                                                                                      'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'employee_number',      'description' => 'CHIPS employee number.',                                                                                'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'gentax_num',           'description' => 'GenTax number.',                                                                                        'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'ita_reg_num',          'description' => 'ITA registration number.',                                                                              'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'legacy_file_number',   'description' => 'MIS case ID (legacy file number).',                                                                     'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'modification_num',     'description' => 'Modification Number.',                                                                                  'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'racf_id',              'description' => 'RACF (mainframe) user ID.',                                                                             'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'reg_num',              'description' => 'Registration number.',                                                                                  'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'reg_stat_num',         'description' => 'Registration status number.',                                                                           'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'saje_id',              'description' => 'SAJE identifier.',                                                                                      'default_method' => 'Format Preserving Alphanumeric (Character Class)'],

            // ── Government identifiers ──────────────────────────────────
            ['name' => 'govt_bceid',           'description' => 'BC government BCeID.',                                                                                  'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'govt_bcuid',           'description' => 'BC government BCUID.',                                                                                  'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'govt_phn',             'description' => 'Provincial personal health number.',                                                                    'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'govt_registration_num', 'description' => 'Government registration number.',                                                                       'default_method' => 'Format Preserving Alphanumeric (Character Class)'],
            ['name' => 'govt_sin',             'description' => 'Federal social insurance number.',                                                                      'default_method' => 'Faker sin Surrogate (Deterministic Lookup)'],

            // ── Personal names ──────────────────────────────────────────
            ['name' => 'name_first',           'description' => 'Personal first name.',                                                                                  'default_method' => 'Faker First Name (Deterministic Lookup)'],
            ['name' => 'name_last',            'description' => 'Personal last name.',                                                                                   'default_method' => 'Faker Last Name (Deterministic Lookup)'],
            ['name' => 'name_middle',          'description' => 'Personal middle name.',                                                                                 'default_method' => 'Faker First Name (Deterministic Lookup)'],
            ['name' => 'new_name',             'description' => 'Name field (general).',                                                                                 'default_method' => 'Faker Full Name (Deterministic Lookup)'],
            ['name' => 'case_name',            'description' => 'Case name (usually based on name of key player).',                                                      'default_method' => 'Faker Full Name (Deterministic Lookup)'],

            // ── Organization names ──────────────────────────────────────
            ['name' => 'org_name',             'description' => 'Organization (usually business, but may be a personal name) name.',                                     'default_method' => 'Faker Company Name (Deterministic Lookup)'],
            ['name' => 'org_name_1',           'description' => 'Additional organization name field.',                                                                   'default_method' => 'Faker Company Name (Deterministic Lookup)'],
            ['name' => 'benefit_plan_name',    'description' => 'Benefit plan name.',                                                                                    'default_method' => 'Deterministic Text Hash'],

            // ── Contact information ─────────────────────────────────────
            ['name' => 'email_address',        'description' => 'Email (SMTP) address.',                                                                                 'default_method' => 'Faker Email (Deterministic Lookup)'],
            ['name' => 'phone_number',         'description' => 'Phone number.',                                                                                         'default_method' => 'Faker Phone Number (Deterministic Lookup)'],

            // ── Address components ──────────────────────────────────────
            ['name' => 'address_line1',        'description' => 'First (primary) line of address.',                                                                      'default_method' => 'Faker Street Address (Deterministic Lookup)'],
            ['name' => 'address_line2',        'description' => 'Second line of address.',                                                                               'default_method' => 'Faker Street Address (Deterministic Lookup)'],
            ['name' => 'address_line3',        'description' => 'Third line of address.',                                                                                'default_method' => 'Faker Street Address (Deterministic Lookup)'],
            ['name' => 'address_city',         'description' => 'City component of address.',                                                                            'default_method' => 'Faker City (Deterministic Lookup)'],
            ['name' => 'address_province',     'description' => 'Province component of address.',                                                                        'default_method' => 'Intra-Column Value Shuffle'],
            ['name' => 'address_country',      'description' => 'Country component of address.',                                                                         'default_method' => 'Intra-Column Value Shuffle'],
            ['name' => 'address_zipcode',      'description' => 'Postal code component of address.',                                                                     'default_method' => 'Faker Postal Code (Deterministic Lookup)'],

            // ── Dates ───────────────────────────────────────────────────
            ['name' => 'date',                 'description' => 'Date field otherwise not special.',                                                                     'default_method' => 'Date Shift (±30 days, Deterministic)'],
            ['name' => 'date_birth',           'description' => 'Personal birth date.',                                                                                  'default_method' => 'Date Shift (±90 Days, Deterministic)'],
            ['name' => 'date_death',           'description' => 'Personal death date.',                                                                                  'default_method' => 'Date Shift (±90 Days, Deterministic)'],
            ['name' => 'case_dt',              'description' => 'Case date.',                                                                                            'default_method' => 'Date Shift (±30 days, Deterministic)'],
            ['name' => 'date_closed',          'description' => 'Entity closed date.',                                                                                   'default_method' => 'Date Shift (±30 days, Deterministic)'],
            ['name' => 'date_created',         'description' => 'Record creation date.',                                                                                 'default_method' => 'Date Shift (±30 days, Deterministic)'],
            ['name' => 'date_db_last_updated', 'description' => 'Database last-updated timestamp.',                                                                      'default_method' => 'Date Shift (±30 days, Deterministic)'],
            ['name' => 'date_end',             'description' => 'End date.',                                                                                             'default_method' => 'Date Shift (±30 days, Deterministic)'],
            ['name' => 'date_last_updated',    'description' => 'Last-updated date.',                                                                                    'default_method' => 'Date Shift (±30 days, Deterministic)'],
            ['name' => 'date_start',           'description' => 'Start date.',                                                                                           'default_method' => 'Date Shift (±30 days, Deterministic)'],
            ['name' => 'date_updated',         'description' => 'Updated date.',                                                                                         'default_method' => 'Date Shift (±30 days, Deterministic)'],
            ['name' => 'update_date',          'description' => 'Update date field.',                                                                                    'default_method' => 'Date Shift (±30 days, Deterministic)'],
            ['name' => 'timestamp',            'description' => 'Timestamp field.',                                                                                      'default_method' => 'Timestamp Shift (±7 days, Deterministic)'],

            // ── Monetary values ─────────────────────────────────────────
            ['name' => 'money',                'description' => 'Monetary value.',                                                                                       'default_method' => 'Numeric Perturbation (±10%)'],
            ['name' => 'money_dollars',        'description' => 'Dollar amount.',                                                                                        'default_method' => 'Numeric Perturbation (±10%)'],

            // ── Free text / comments ────────────────────────────────────
            ['name' => 'comment',              'description' => 'Comment or notes field.',                                                                               'default_method' => 'Faker Comment/Notes (Deterministic Lookup)'],
            ['name' => 'comment_sr',           'description' => 'Service request comment.',                                                                              'default_method' => 'Faker Comment/Notes (Deterministic Lookup)'],

            // ── User / system identifiers ───────────────────────────────
            ['name' => 'user_id',              'description' => 'User identifier.',                                                                                      'default_method' => 'Faker Username (Deterministic Lookup)'],
            ['name' => 'person_uid',           'description' => 'Person unique identifier.',                                                                             'default_method' => 'Deterministic Text Hash'],
            ['name' => 'integration_id',       'description' => 'ICM integration ID. Should be internal-only, but might be worth munging just in case.',                 'default_method' => 'Deterministic Text Hash'],
            ['name' => 'guid',                 'description' => 'Globally unique identifier (GUID/UUID).',                                                               'default_method' => 'UUID/GUID Replacement'],
            ['name' => 'url',                  'description' => 'Web site link.',                                                                                        'default_method' => 'URL Path Anonymization'],

            // ── Siebel structural keys ──────────────────────────────────
            ['name' => 'row_id',               'description' => 'Inferred primary or foreign key (observed but not documented).',                                        'default_method' => 'SQL Deterministic Siebel ROW_ID (SHA-256)'],
            ['name' => 'row_id_pk',            'description' => 'Is a primary key; PR_KEY (G) is not empty.',                                                            'default_method' => 'SQL Deterministic Siebel ROW_ID (SHA-256)'],
            ['name' => 'row_id_fk',            'description' => 'Is a foreign key referencing another table; REF_TAB_NAME (H) is not empty.',                            'default_method' => 'SQL Seed Map Lookup (FK)'],

            // ── Shuffle / categorical ───────────────────────────────────
            ['name' => 'shuffle',              'description' => 'Randomly pick from values present in column. Usually used for LOV fields.',                              'default_method' => 'Intra-Column Value Shuffle'],
            ['name' => 'shuffle_elmsd_amt',    'description' => 'Shuffle ELMSD amount values.',                                                                          'default_method' => 'Intra-Column Value Shuffle'],
            ['name' => 'shuffle_transit_rate', 'description' => 'Shuffle transit rate values.',                                                                           'default_method' => 'Intra-Column Value Shuffle'],
            ['name' => 'sex_mf',               'description' => 'Sex indicator (M/F).',                                                                                  'default_method' => 'Intra-Column Value Shuffle'],
            ['name' => 'business_unit',        'description' => 'Business unit (categorical).',                                                                          'default_method' => 'Intra-Column Value Shuffle'],
            ['name' => 'code',                 'description' => 'Code value (categorical).',                                                                             'default_method' => 'Intra-Column Value Shuffle'],
            ['name' => 'cp_adp_caseload',      'description' => 'CP/ADP caseload assignment.',                                                                           'default_method' => 'Intra-Column Value Shuffle'],
            ['name' => 'flag',                 'description' => '\'Y\', \'N\', or null.',                                                                                'default_method' => 'Intra-Column Value Shuffle'],
            ['name' => 'yes_no',               'description' => '\'Yes\', \'No\', or null.',                                                                             'default_method' => 'Intra-Column Value Shuffle'],
            ['name' => 'yes_no_tbd',           'description' => 'Yes/No/TBD categorical field.',                                                                         'default_method' => 'Intra-Column Value Shuffle'],

            // ── Redact / suppress ───────────────────────────────────────
            ['name' => 'base64',               'description' => 'Content is encoded in Base64. Possibly to obfuscate content, but trivially decoded.',                   'default_method' => 'Redact (Fixed Token)'],
            ['name' => 'db_last_update_source', 'description' => 'Database last-update source identifier.',                                                               'default_method' => 'Redact (Fixed Token)'],
            ['name' => 'eim_internal',         'description' => 'EIM (Enterprise Integration Manager) internal field.',                                                  'default_method' => 'Redact (Fixed Token)'],
            ['name' => 'webdav_resource',      'description' => 'WebDAV resource content.',                                                                              'default_method' => 'Redact (Fixed Token)'],
            ['name' => 'webdav_rsource',       'description' => 'WebDAV resource content (alternate spelling).',                                                         'default_method' => 'Redact (Fixed Token)'],
            ['name' => 'unused',               'description' => 'Field has no values (NUM_DISTINCT is 0 or empty).',                                                     'default_method' => 'Nullify (Set NULL)'],

            // ── Pass-through / informational (no method) ────────────────
            ['name' => 'no_change',            'description' => 'Do not change the value, pass through unchanged. Included so we can mark that we have decided on a rule.', 'default_method' => null],
            ['name' => 'empty',                'description' => 'Table contains no records; column does not need to be copied.',                                         'default_method' => 'Exclude Column (Do Not Copy)'],
            ['name' => 'ZZZ',                  'description' => 'End of list marker.',                                                                                    'default_method' => null],
        ];
    }
}
