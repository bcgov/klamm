<?php

namespace Database\Seeders\Anonymization;

use App\Models\Anonymizer\AnonymizationMethods;
use Illuminate\Database\Seeder;

/**
 * Seeds anonymization methods that use Faker-generated lookup packages.
 *
 * These methods use pre-generated synthetic data stored in packages
 * for deterministic and random lookups, providing realistic masked values.
 *
 * Package metadata is seeded separately via AnonymizationFakerPackageSeeder.
 *
 */
class AnonymizationFakerLookupMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            // ================================================================
            // DETERMINISTIC LOOKUP METHODS (use seed for consistent results)
            // ================================================================
            [
                'name' => 'Faker First Name (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces first names with synthetic values from a pre-generated lookup table.',
                'what_it_does' => 'Maps original first names to realistic synthetic first names deterministically.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ Faker-generated first names.',
                'sql_block' => <<<SQL
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
                'sql_block' => <<<SQL
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
                'sql_block' => <<<SQL
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
                'sql_block' => <<<SQL
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
                'sql_block' => <<<SQL
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
                'sql_block' => <<<SQL
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
                'sql_block' => <<<SQL
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
                'sql_block' => <<<SQL
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
                'sql_block' => <<<SQL
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
                'description' => 'Replaces sin/SIN values with non-valid format-preserving surrogates.',
                'what_it_does' => 'Maps original sin values to synthetic sin-format strings that are not valid.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ invalid-but-formatted sin surrogates.',
                'sql_block' => <<<SQL
-- Deterministic sin surrogate lookup from Faker package
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
                'seed_notes' => 'Uses invalid sin area codes (900-999). Not valid for verification.',
            ],
            [
                'name' => 'Faker Credit Card (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces credit card numbers with masked non-functional surrogates.',
                'what_it_does' => 'Maps original CC values to masked format-preserving surrogates.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ masked card number patterns.',
                'sql_block' => <<<SQL
-- Deterministic credit card lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_CREDIT_CARDS.GET_CREDIT_CARDS(
       {{JOB_SEED_LITERAL}} || '|CC|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Masked format with visible last 4 digits. Not functional.',
            ],
            [
                'name' => 'Faker Account Number (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces account/reference numbers with synthetic values.',
                'what_it_does' => 'Maps original account numbers to synthetic account number patterns.',
                'how_it_works' => 'Uses a seed-based hash to select from 10,000+ synthetic account numbers.',
                'sql_block' => <<<SQL
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
                'sql_block' => <<<SQL
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
                'sql_block' => <<<SQL
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
            [
                'name' => 'Faker Job Title (Deterministic Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces job titles with synthetic values.',
                'what_it_does' => 'Maps original job titles to realistic synthetic job titles.',
                'how_it_works' => 'Uses a seed-based hash to select from 2,000+ Faker-generated job titles.',
                'sql_block' => <<<SQL
-- Deterministic job title lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_JOB_TITLES.GET_JOB_TITLES(
       {{JOB_SEED_LITERAL}} || '|TITLE|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Max length 75 chars.',
            ],

            // ================================================================
            // RANDOM LOOKUP METHODS (non-deterministic, each run different)
            // ================================================================
            [
                'name' => 'Faker First Name (Random)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces first names with random synthetic values.',
                'what_it_does' => 'Replaces original first names with random synthetic first names.',
                'how_it_works' => 'Selects randomly from 10,000+ Faker-generated first names.',
                'sql_block' => <<<SQL
-- Random first name from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_FIRST_NAMES.GET_FIRST_NAMES_RANDOM(
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic. Different results each execution.',
            ],
            [
                'name' => 'Faker Last Name (Random)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces last names with random synthetic values.',
                'what_it_does' => 'Replaces original last names with random synthetic last names.',
                'how_it_works' => 'Selects randomly from 10,000+ Faker-generated last names.',
                'sql_block' => <<<SQL
-- Random last name from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_LAST_NAMES.GET_LAST_NAMES_RANDOM(
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic. Different results each execution.',
            ],
            [
                'name' => 'Faker Email (Random)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces emails with random synthetic values using safe domains.',
                'what_it_does' => 'Replaces original emails with random synthetic emails.',
                'how_it_works' => 'Selects randomly from 10,000+ Faker-generated safe emails.',
                'sql_block' => <<<SQL
-- Random email from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_EMAILS.GET_EMAILS_RANDOM(
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic. Uses @example.com/org/net domains.',
            ],
            [
                'name' => 'Faker Phone (Random)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces phone numbers with random synthetic values.',
                'what_it_does' => 'Replaces original phone numbers with random synthetic phones.',
                'how_it_works' => 'Selects randomly from 10,000+ synthetic phone numbers.',
                'sql_block' => <<<SQL
-- Random phone from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_PHONES.GET_PHONES_RANDOM(
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic. Uses 555/toll-free exchanges.',
            ],
            [
                'name' => 'Faker Address (Random)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces addresses with random synthetic values.',
                'what_it_does' => 'Replaces original addresses with random synthetic addresses.',
                'how_it_works' => 'Selects randomly from 10,000+ Faker-generated addresses.',
                'sql_block' => <<<SQL
-- Random address from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_ADDRESSES.GET_ADDRESSES_RANDOM(
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic. Max length 200 chars.',
            ],

            // ================================================================
            // SIEBEL STATUS/CATEGORY METHODS (deterministic from fixed lists)
            // ================================================================
            [
                'name' => 'Siebel Status Value (Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_SHUFFLE_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_SHUFFLE_MASKING],
                'description' => 'Replaces status values with valid Siebel status terms.',
                'what_it_does' => 'Maps original status values to valid Siebel-compatible status values.',
                'how_it_works' => 'Uses a seed-based hash to select from standard Siebel status values.',
                'sql_block' => <<<SQL
-- Deterministic status value lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_STATUSES.GET_STATUSES(
       {{JOB_SEED_LITERAL}} || '|STATUS|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Uses standard Siebel status values (Active, Inactive, Pending, etc.)',
            ],
            [
                'name' => 'Siebel Priority Value (Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_SHUFFLE_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_SHUFFLE_MASKING],
                'description' => 'Replaces priority values with valid Siebel priority terms.',
                'what_it_does' => 'Maps original priority values to valid Siebel-compatible priority values.',
                'how_it_works' => 'Uses a seed-based hash to select from standard Siebel priority values.',
                'sql_block' => <<<SQL
-- Deterministic priority value lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_PRIORITIES.GET_PRIORITIES(
       {{JOB_SEED_LITERAL}} || '|PRIORITY|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Uses standard Siebel priority values (1-ASAP, 2-High, 3-Medium, 4-Low)',
            ],
            [
                'name' => 'Siebel Type Value (Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_SHUFFLE_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_SHUFFLE_MASKING],
                'description' => 'Replaces type/category values with valid Siebel type terms.',
                'what_it_does' => 'Maps original type values to valid Siebel-compatible type values.',
                'how_it_works' => 'Uses a seed-based hash to select from standard Siebel entity types.',
                'sql_block' => <<<SQL
-- Deterministic type value lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ANON_DATA.PKG_ANON_TYPES.GET_TYPES(
       {{JOB_SEED_LITERAL}} || '|TYPE|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Uses standard Siebel type values (Individual, Organization, etc.)',
            ],

            // ================================================================
            // NUMERIC METHODS
            // ================================================================
            [
                'name' => 'Faker Age (Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Replaces age values with valid ages (18-99).',
                'what_it_does' => 'Maps original ages to valid age values within the 18-99 range.',
                'how_it_works' => 'Uses a seed-based hash to select deterministically from valid ages.',
                'sql_block' => <<<SQL
-- Deterministic age value lookup from Faker package
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = TO_NUMBER(ANON_DATA.PKG_ANON_AGES.GET_AGES(
       {{JOB_SEED_LITERAL}} || '|AGE|' || TO_CHAR({{SEED_EXPR}}) || '|' || TO_CHAR(tgt.{{COLUMN}}),
       3
   ))
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Returns NUMBER. Valid range 18-99.',
            ],
            [
                'name' => 'Numeric Perturbation (±10%)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Perturbs numeric values by ±10% using deterministic hashing.',
                'what_it_does' => 'Adjusts numeric values by a stable pseudo-random percentage.',
                'how_it_works' => 'Uses STANDARD_HASH to generate a consistent ±10% adjustment.',
                'sql_block' => <<<SQL
-- Numeric perturbation ±10%
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

            // ================================================================
            // DATE/TIME METHODS
            // ================================================================
            [
                'name' => 'Date Shift (±30 days, Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Shifts dates by ±30 days using deterministic hashing.',
                'what_it_does' => 'Adjusts dates by a stable pseudo-random offset within ±30 days.',
                'how_it_works' => 'Uses STANDARD_HASH to generate a consistent day offset.',
                'sql_block' => <<<SQL
-- Date shift ±30 days
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
                'name' => 'Timestamp Shift (±7 days, Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Shifts timestamps by ±7 days using deterministic hashing.',
                'what_it_does' => 'Adjusts timestamps by a stable pseudo-random offset.',
                'how_it_works' => 'Uses STANDARD_HASH to generate a consistent time offset.',
                'sql_block' => <<<SQL
-- Timestamp shift ±7 days
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

            // ================================================================
            // CLOB/LARGE TEXT METHODS
            // ================================================================
            [
                'name' => 'CLOB Comment Replacement',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Replaces CLOB content with lorem ipsum placeholder text.',
                'what_it_does' => 'Replaces large text fields with generic placeholder content.',
                'how_it_works' => 'Uses a deterministic lookup to select lorem ipsum text.',
                'sql_block' => <<<SQL
-- CLOB replacement with lorem ipsum
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = TO_CLOB(ANON_DATA.PKG_ANON_COMMENTS.GET_COMMENTS(
       {{JOB_SEED_LITERAL}} || '|CLOB|' || TO_CHAR({{SEED_EXPR}}),
       255
   ))
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'For CLOB columns. Truncates to 255 chars; extend as needed.',
            ],

            // ================================================================
            // NULL-SAFE CONDITIONAL METHODS
            // ================================================================
            [
                'name' => 'Faker First Name (Nullable-Safe)',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING, AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces first names, preserving NULL values.',
                'what_it_does' => 'Maps non-null first names to synthetic values; NULLs remain NULL.',
                'how_it_works' => 'Uses NVL2 to conditionally apply masking only to non-null values.',
                'sql_block' => <<<SQL
-- Nullable-safe first name replacement
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = NVL2(
       tgt.{{COLUMN}},
       ANON_DATA.PKG_ANON_FIRST_NAMES.GET_FIRST_NAMES(
           {{JOB_SEED_LITERAL}} || '|FN|' || TO_CHAR({{SEED_EXPR}}) || '|' || tgt.{{COLUMN}},
           {{COLUMN_MAX_LEN_EXPR}}
       ),
       NULL
   );
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Preserves NULL values. No WHERE clause needed.',
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

        $this->command->info('Seeded ' . count($methods) . ' Faker-based anonymization methods.');
    }
}
