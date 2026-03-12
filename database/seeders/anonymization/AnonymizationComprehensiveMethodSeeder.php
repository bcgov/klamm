<?php

namespace Database\Seeders\Anonymization;

use App\Models\Anonymizer\AnonymizationMethods;
use Illuminate\Database\Seeder;

/**
 * Seeds comprehensive anonymization methods covering all common data masking scenarios.
 *
 * This seeder provides methods for:
 * - Format-preserving masking (SIN, phone, credit card patterns)
 * - Data generalization and bucketing (ages, dates, geography)
 * - Specialized PII masking (IP addresses, URLs, UUIDs)
 * - Healthcare/financial identifiers
 * - Free-text and CLOB handling
 * - Numeric perturbation and rounding
 * - Conditional and rule-based masking
 * - Data shuffling techniques
 * - K-anonymity supporting methods
 */
class AnonymizationComprehensiveMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = array_merge(
            $this->formatPreservingMethods(),
            $this->generalizationMethods(),
            $this->specializedPiiMethods(),
            $this->healthcareFinancialMethods(),
            $this->freeTextMethods(),
            $this->numericMethods(),
            $this->conditionalMethods(),
            $this->shuffleMethods(),
            $this->temporalMethods(),
            $this->referentialIntegrityMethods(),
        );

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

    /**
     * Format-preserving masking methods that maintain the structure/format of data.
     */
    protected function formatPreservingMethods(): array
    {
        return [
            [
                'name' => 'Format Preserving SIN/SIN (XXX-XX-XXXX)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces SIN/SIN with format-preserving synthetic value.',
                'what_it_does' => 'Generates a fake SIN/SIN that looks real but uses invalid area numbers (900-999 range).',
                'how_it_works' => 'Hashes the original value to produce deterministic fake digits in invalid ranges.',
                'sql_block' => <<<SQL
-- Format-preserving SIN replacement (uses invalid 900-999 area codes)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = '9' ||
       SUBSTR(LPAD(TO_CHAR(ABS(MOD(ORA_HASH({{JOB_SEED_LITERAL}} || tgt.{{COLUMN}}), 100))), 2, '0'), 1, 2) || '-' ||
       SUBSTR(LPAD(TO_CHAR(ABS(MOD(ORA_HASH({{JOB_SEED_LITERAL}} || tgt.{{COLUMN}} || 'A'), 100))), 2, '0'), 1, 2) || '-' ||
       SUBSTR(LPAD(TO_CHAR(ABS(MOD(ORA_HASH({{JOB_SEED_LITERAL}} || tgt.{{COLUMN}} || 'B'), 10000))), 4, '0'), 1, 4)
 WHERE {{COLUMN}} IS NOT NULL
   AND REGEXP_LIKE({{COLUMN}}, '^\d{3}-\d{2}-\d{4}$');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Output uses 9XX prefix (reserved/invalid). Deterministic for same input.',
            ],
            [
                'name' => 'Format Preserving Phone (XXX-XXX-XXXX)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces phone numbers with format-preserving synthetic values.',
                'what_it_does' => 'Generates fake phone numbers using reserved 555-01XX exchange.',
                'how_it_works' => 'Preserves original format while replacing digits with 555-01XX pattern.',
                'sql_block' => <<<SQL
-- Format-preserving phone replacement (uses reserved 555-01XX exchange)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = REGEXP_REPLACE(
       {{COLUMN}},
       '(\d{3})[-.\s]?(\d{3})[-.\s]?(\d{4})',
       '555-01' || SUBSTR(LPAD(TO_CHAR(ABS(MOD(ORA_HASH({{JOB_SEED_LITERAL}} || tgt.{{COLUMN}}), 100))), 2, '0'), 1, 2) || '-' ||
       SUBSTR(LPAD(TO_CHAR(ABS(MOD(ORA_HASH({{JOB_SEED_LITERAL}} || tgt.{{COLUMN}} || 'X'), 10000))), 4, '0'), 1, 4)
   )
 WHERE {{COLUMN}} IS NOT NULL
   AND REGEXP_LIKE({{COLUMN}}, '\d{3}[-.\s]?\d{3}[-.\s]?\d{4}');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Uses 555-01XX (reserved fictional exchange). Handles various delimiters.',
            ],
            [
                'name' => 'Format Preserving Credit Card (Masked Middle)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Masks middle digits of credit card while preserving first/last 4.',
                'what_it_does' => 'Keeps first 4 (BIN) and last 4 digits visible, masks middle with X.',
                'how_it_works' => 'Pattern-based replacement preserving card structure for testing.',
                'sql_block' => <<<SQL
-- Credit card partial masking (preserve first 4 / last 4)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = SUBSTR({{COLUMN}}, 1, 4) ||
       '-XXXX-XXXX-' ||
       SUBSTR(REPLACE(REPLACE(REPLACE({{COLUMN}}, '-', ''), ' ', ''), '.', ''), -4)
 WHERE {{COLUMN}} IS NOT NULL
   AND LENGTH(REPLACE(REPLACE(REPLACE({{COLUMN}}, '-', ''), ' ', ''), '.', '')) >= 13;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'BIN and last 4 preserved for card type identification. Middle digits masked.',
            ],
            [
                'name' => 'Format Preserving Credit Card (Full Replacement)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces entire credit card with test card number.',
                'what_it_does' => 'Generates a deterministic test card number (4111-prefix for Visa test range).',
                'how_it_works' => 'Uses hash to generate middle digits, applies Luhn checksum.',
                'sql_block' => <<<SQL
-- Full credit card replacement with test numbers (4111 Visa test prefix)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = '4111-1111-' ||
       SUBSTR(LPAD(TO_CHAR(ABS(MOD(ORA_HASH({{JOB_SEED_LITERAL}} || tgt.{{COLUMN}}), 10000))), 4, '0'), 1, 4) || '-' ||
       SUBSTR(LPAD(TO_CHAR(ABS(MOD(ORA_HASH({{JOB_SEED_LITERAL}} || tgt.{{COLUMN}} || 'CC'), 10000))), 4, '0'), 1, 4)
 WHERE {{COLUMN}} IS NOT NULL
   AND LENGTH(REPLACE(REPLACE(REPLACE({{COLUMN}}, '-', ''), ' ', ''), '.', '')) >= 13;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Uses 4111-1111 test prefix. Note: Luhn checksum not enforced.',
            ],
            [
                'name' => 'Format Preserving Alphanumeric (Character Class)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces characters while preserving alpha/numeric/special class positions.',
                'what_it_does' => 'Maintains format structure: A→A, 9→9, special→special.',
                'how_it_works' => 'Translates each character class to deterministic replacement within same class.',
                'sql_block' => <<<SQL
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
                'name' => 'Postal Code Generalization (First 3 Chars)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION, AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Keeps first 3 characters of postal/ZIP code, masks remainder.',
                'what_it_does' => 'Preserves geographic region (FSA for Canada, ZIP3 for US) while masking specifics.',
                'how_it_works' => 'Truncates and pads with placeholder characters.',
                'sql_block' => <<<SQL
-- Postal code generalization (keep first 3 chars)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = CASE
       WHEN LENGTH({{COLUMN}}) >= 6 THEN SUBSTR({{COLUMN}}, 1, 3) || ' XXX'
       WHEN LENGTH({{COLUMN}}) >= 5 THEN SUBSTR({{COLUMN}}, 1, 3) || 'XX'
       ELSE SUBSTR({{COLUMN}}, 1, 3)
   END
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Supports both US ZIP (5-digit) and Canadian postal (A1A 1A1) formats.',
            ],
        ];
    }

    /**
     * Data generalization and bucketing methods for quasi-identifiers.
     */
    protected function generalizationMethods(): array
    {
        return [
            [
                'name' => 'Age Bucketing (10-Year Ranges)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Converts exact age to 10-year age ranges.',
                'what_it_does' => 'Generalizes ages into buckets: 0-9, 10-19, 20-29, etc.',
                'how_it_works' => 'Calculates age bucket floor and formats as range string.',
                'sql_block' => <<<SQL
-- Age bucketing (10-year ranges)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = TO_CHAR(FLOOR({{COLUMN}} / 10) * 10) || '-' || TO_CHAR(FLOOR({{COLUMN}} / 10) * 10 + 9)
 WHERE {{COLUMN}} IS NOT NULL
   AND {{COLUMN}} BETWEEN 0 AND 120;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Output format: "20-29", "30-39", etc. Supports VARCHAR columns.',
            ],
            [
                'name' => 'Age Bucketing (5-Year Ranges)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Converts exact age to 5-year age ranges for finer granularity.',
                'what_it_does' => 'Generalizes ages into 5-year buckets: 0-4, 5-9, 10-14, etc.',
                'how_it_works' => 'Calculates 5-year bucket floor and formats as range string.',
                'sql_block' => <<<SQL
-- Age bucketing (5-year ranges)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = TO_CHAR(FLOOR({{COLUMN}} / 5) * 5) || '-' || TO_CHAR(FLOOR({{COLUMN}} / 5) * 5 + 4)
 WHERE {{COLUMN}} IS NOT NULL
   AND {{COLUMN}} BETWEEN 0 AND 120;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Output format: "20-24", "25-29", etc. Finer than 10-year ranges.',
            ],
            [
                'name' => 'Date to Year Only',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Truncates date to year only (Jan 1).',
                'what_it_does' => 'Removes month/day precision by setting to January 1st of same year.',
                'how_it_works' => 'Truncates date to year boundary.',
                'sql_block' => <<<SQL
-- Date generalization to year only
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = TRUNC({{COLUMN}}, 'YEAR')
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'All dates become January 1st of their original year.',
            ],
            [
                'name' => 'Date to Quarter Only',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Truncates date to start of quarter.',
                'what_it_does' => 'Removes day precision by setting to first day of calendar quarter.',
                'how_it_works' => 'Truncates date to quarter boundary (Q1=Jan 1, Q2=Apr 1, etc.).',
                'sql_block' => <<<SQL
-- Date generalization to quarter
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = TRUNC({{COLUMN}}, 'Q')
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Dates become first day of their quarter.',
            ],
            [
                'name' => 'Date to Month Only',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Truncates date to first of month.',
                'what_it_does' => 'Removes day precision by setting to first day of month.',
                'how_it_works' => 'Truncates date to month boundary.',
                'sql_block' => <<<SQL
-- Date generalization to month
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = TRUNC({{COLUMN}}, 'MONTH')
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'All dates become the 1st of their original month.',
            ],
            [
                'name' => 'Birth Date to Age (As of Today)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Converts birth date to current age in years.',
                'what_it_does' => 'Replaces exact birth date with calculated integer age.',
                'how_it_works' => 'Calculates years between birth date and current date.',
                'sql_block' => <<<SQL
-- Birth date to age conversion
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = FLOOR(MONTHS_BETWEEN(SYSDATE, {{COLUMN}}) / 12)
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Column becomes numeric age. Consider target column data type.',
            ],
            [
                'name' => 'Geographic Region (State/Province Only)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Replaces detailed address with state/province only.',
                'what_it_does' => 'Suppresses street/city while preserving regional identifier.',
                'how_it_works' => 'Retains only the state/province portion of address data.',
                'sql_block' => <<<SQL
-- Address to region generalization (keeps last comma-separated segment)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = TRIM(REGEXP_SUBSTR({{COLUMN}}, '[^,]+$'))
 WHERE {{COLUMN}} IS NOT NULL
   AND INSTR({{COLUMN}}, ',') > 0;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Assumes "Street, City, State" format. May need adjustment per data.',
            ],
            [
                'name' => 'Salary Range Bucketing ($25K)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Converts exact salary to $25K ranges.',
                'what_it_does' => 'Generalizes salaries into $25K buckets: 50000-74999, etc.',
                'how_it_works' => 'Calculates salary bucket floor using 25000 intervals.',
                'sql_block' => <<<SQL
-- Salary bucketing ($25K ranges)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = TO_CHAR(FLOOR({{COLUMN}} / 25000) * 25000) || '-' ||
                    TO_CHAR(FLOOR({{COLUMN}} / 25000) * 25000 + 24999)
 WHERE {{COLUMN}} IS NOT NULL
   AND {{COLUMN}} > 0;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Output is VARCHAR like "50000-74999". Column must support text.',
            ],
        ];
    }

    /**
     * Specialized PII masking methods for technical identifiers.
     */
    protected function specializedPiiMethods(): array
    {
        return [
            [
                'name' => 'IP Address Anonymization (Zero Last Octet)',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Zeros the last octet of IPv4 addresses.',
                'what_it_does' => 'Preserves network segment while anonymizing host identifier.',
                'how_it_works' => 'Replaces last octet with 0 to create /24 network generalization.',
                'sql_block' => <<<SQL
-- IPv4 last octet anonymization
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = REGEXP_REPLACE({{COLUMN}}, '(\d+\.\d+\.\d+\.)\d+', '\10')
 WHERE {{COLUMN}} IS NOT NULL
   AND REGEXP_LIKE({{COLUMN}}, '^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Converts 192.168.1.123 to 192.168.1.0. IPv4 only.',
            ],
            [
                'name' => 'IP Address Full Replacement',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces IP addresses with deterministic private-range IPs.',
                'what_it_does' => 'Generates synthetic IP in 10.x.x.x private range.',
                'how_it_works' => 'Hashes original IP to generate deterministic octets.',
                'sql_block' => <<<SQL
-- IP address deterministic replacement (10.x.x.x private range)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = '10.' ||
       TO_CHAR(MOD(ORA_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}), 256)) || '.' ||
       TO_CHAR(MOD(ORA_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}} || 'A'), 256)) || '.' ||
       TO_CHAR(MOD(ORA_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}} || 'B'), 256))
 WHERE {{COLUMN}} IS NOT NULL
   AND REGEXP_LIKE({{COLUMN}}, '^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Uses RFC 1918 private 10.0.0.0/8 range. Deterministic.',
            ],
            [
                'name' => 'MAC Address Anonymization',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces MAC addresses with locally-administered synthetic values.',
                'what_it_does' => 'Generates fake MAC with locally-administered bit set.',
                'how_it_works' => 'Hashes to generate deterministic hex pairs with X2/X6/XA/XE prefix.',
                'sql_block' => <<<SQL
-- MAC address anonymization (locally-administered prefix)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = '02:' ||
       SUBSTR(LOWER(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'MD5'))), 1, 2) || ':' ||
       SUBSTR(LOWER(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'MD5'))), 3, 2) || ':' ||
       SUBSTR(LOWER(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'MD5'))), 5, 2) || ':' ||
       SUBSTR(LOWER(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'MD5'))), 7, 2) || ':' ||
       SUBSTR(LOWER(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'MD5'))), 9, 2)
 WHERE {{COLUMN}} IS NOT NULL
   AND REGEXP_LIKE(UPPER({{COLUMN}}), '^([0-9A-F]{2}[:\-]){5}[0-9A-F]{2}$');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => '02:xx prefix indicates locally-administered (non-manufacturer) MAC.',
            ],
            [
                'name' => 'UUID/GUID Replacement',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces UUIDs with deterministic version-4 format values.',
                'what_it_does' => 'Generates synthetic UUIDs that preserve version-4 format.',
                'how_it_works' => 'Uses hash to generate hex values with proper version/variant bits.',
                'sql_block' => <<<SQL
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
                'sql_block' => <<<SQL
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
            [
                'name' => 'Email Domain Preservation',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Anonymizes email local part while preserving domain.',
                'what_it_does' => 'Replaces username part with hash, keeps original domain.',
                'how_it_works' => 'Extracts domain, generates deterministic local part via hash.',
                'sql_block' => <<<SQL
-- Email local part anonymization (preserve domain)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = 'user_' ||
       SUBSTR(LOWER(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'SHA256'))), 1, 8) ||
       '@' || REGEXP_SUBSTR({{COLUMN}}, '@(.+)$', 1, 1, NULL, 1)
 WHERE {{COLUMN}} IS NOT NULL
   AND INSTR({{COLUMN}}, '@') > 1;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Preserves original domain. Useful for domain-based routing tests.',
            ],
            [
                'name' => 'File Path Anonymization',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Anonymizes file paths while preserving extension.',
                'what_it_does' => 'Replaces path/filename with hash, keeps file extension.',
                'how_it_works' => 'Extracts extension, generates deterministic path prefix.',
                'sql_block' => <<<SQL
-- File path anonymization (preserve extension)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = '/masked/file_' ||
       SUBSTR(LOWER(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'MD5'))), 1, 12) ||
       CASE
           WHEN INSTR({{COLUMN}}, '.', -1) > 0
           THEN SUBSTR({{COLUMN}}, INSTR({{COLUMN}}, '.', -1))
           ELSE ''
       END
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Output format: /masked/file_[hash].[ext]. Deterministic.',
            ],
        ];
    }

    /**
     * Healthcare and financial identifier masking methods.
     */
    protected function healthcareFinancialMethods(): array
    {
        return [
            [
                'name' => 'Medical Record Number (MRN) Replacement',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces MRN with deterministic synthetic value.',
                'what_it_does' => 'Generates test MRN prefixed with TEST- for easy identification.',
                'how_it_works' => 'Hashes original MRN to produce stable numeric replacement.',
                'sql_block' => <<<SQL
-- Medical Record Number replacement
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = 'TEST-' ||
       LPAD(TO_CHAR(ABS(MOD(ORA_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}), 100000000))), 8, '0')
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Format: TEST-XXXXXXXX. Deterministic for referential integrity.',
            ],
            [
                'name' => 'Health Card Number (Canadian)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces health card numbers with format-preserving synthetic values.',
                'what_it_does' => 'Generates fake health card number matching 10-digit format.',
                'how_it_works' => 'Uses 9999-prefix (invalid range) with deterministic digits.',
                'sql_block' => <<<SQL
-- Canadian Health Card Number replacement (10-digit format)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = '9999' ||
       LPAD(TO_CHAR(ABS(MOD(ORA_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}), 1000000))), 6, '0')
 WHERE {{COLUMN}} IS NOT NULL
   AND REGEXP_LIKE({{COLUMN}}, '^\d{10}$');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => '9999-prefix is invalid/test range. Deterministic.',
            ],
            [
                'name' => 'Insurance Policy Number',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces insurance policy numbers with synthetic values.',
                'what_it_does' => 'Generates test policy number with TESTPOL- prefix.',
                'how_it_works' => 'Hashes original to produce stable alphanumeric replacement.',
                'sql_block' => <<<SQL
-- Insurance policy number replacement
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = 'TESTPOL-' ||
       UPPER(SUBSTR(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'MD5')), 1, 10))
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Format: TESTPOL-[10 hex chars]. Deterministic.',
            ],
            [
                'name' => 'Bank Account Number (Generic)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces bank account numbers with test values.',
                'what_it_does' => 'Generates synthetic account number using 0000 test prefix.',
                'how_it_works' => 'Preserves length, uses test-range prefix + hash-derived digits.',
                'sql_block' => <<<SQL
-- Bank account number replacement (preserves length)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = '0000' ||
       SUBSTR(
           LPAD(TO_CHAR(ABS(ORA_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}))), 20, '0'),
           1,
           GREATEST(LENGTH({{COLUMN}}) - 4, 4)
       )
 WHERE {{COLUMN}} IS NOT NULL
   AND REGEXP_LIKE({{COLUMN}}, '^\d+$');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Uses 0000 test prefix. Attempts to preserve original length.',
            ],
            [
                'name' => 'Bank Routing Number (ABA)',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces ABA routing numbers with test values.',
                'what_it_does' => 'Generates synthetic 9-digit routing number with test Federal Reserve prefix.',
                'how_it_works' => 'Uses 00 prefix (invalid) with deterministic digits.',
                'sql_block' => <<<SQL
-- ABA Routing Number replacement (9-digit)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = '00' ||
       LPAD(TO_CHAR(ABS(MOD(ORA_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}), 10000000))), 7, '0')
 WHERE {{COLUMN}} IS NOT NULL
   AND REGEXP_LIKE({{COLUMN}}, '^\d{9}$');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => '00-prefix is invalid Federal Reserve district. Deterministic.',
            ],
            [
                'name' => 'IBAN Replacement',
                'category' => AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
                'categories' => [AnonymizationMethods::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION],
                'description' => 'Replaces IBANs while preserving country code.',
                'what_it_does' => 'Generates synthetic IBAN with original country code + test values.',
                'how_it_works' => 'Preserves 2-letter country code, replaces remainder with hash.',
                'sql_block' => <<<SQL
-- IBAN replacement (preserve country code)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = SUBSTR({{COLUMN}}, 1, 2) || '00' ||
       UPPER(SUBSTR(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'MD5')), 1, LENGTH({{COLUMN}}) - 4))
 WHERE {{COLUMN}} IS NOT NULL
   AND LENGTH({{COLUMN}}) >= 15
   AND REGEXP_LIKE(SUBSTR({{COLUMN}}, 1, 2), '^[A-Z]{2}$');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Preserves country code. Check digits (00) are invalid.',
            ],
            [
                'name' => 'Driver License Number',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces driver license numbers with synthetic values.',
                'what_it_does' => 'Generates test DL number with TEST-DL prefix.',
                'how_it_works' => 'Hashes original to produce deterministic alphanumeric value.',
                'sql_block' => <<<SQL
-- Driver License Number replacement
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = 'TEST-DL-' ||
       UPPER(SUBSTR(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'MD5')), 1, 12))
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Format: TEST-DL-[12 hex chars]. Deterministic.',
            ],
            [
                'name' => 'Passport Number',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces passport numbers with test values.',
                'what_it_does' => 'Generates synthetic passport with TEST prefix.',
                'how_it_works' => 'Uses TEST prefix with hash-derived alphanumeric suffix.',
                'sql_block' => <<<SQL
-- Passport Number replacement
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = 'TEST' ||
       UPPER(SUBSTR(RAWTOHEX(STANDARD_HASH({{JOB_SEED_LITERAL}} || {{COLUMN}}, 'MD5')), 1, 8))
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Format: TEST[8 hex chars]. Deterministic.',
            ],
        ];
    }

    /**
     * Free-text and CLOB handling methods.
     */
    protected function freeTextMethods(): array
    {
        return [
            [
                'name' => 'Lorem Ipsum Replacement (Short)',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Replaces text with short lorem ipsum placeholder.',
                'what_it_does' => 'Overwrites free-text with standard 50-char lorem ipsum.',
                'how_it_works' => 'Simple replacement with fixed text preserving non-null semantics.',
                'sql_block' => <<<SQL
-- Lorem ipsum replacement (short)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.'
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Fixed 56-character replacement. Use for short note fields.',
            ],
            [
                'name' => 'Lorem Ipsum Replacement (Medium)',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Replaces text with medium-length lorem ipsum.',
                'what_it_does' => 'Overwrites with ~200 characters of lorem ipsum.',
                'how_it_works' => 'Simple replacement for medium-sized text fields.',
                'sql_block' => <<<SQL
-- Lorem ipsum replacement (medium ~200 chars)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.'
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Fixed ~200-character replacement. Use for description fields.',
            ],
            [
                'name' => 'Truncate and Mask Text',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Truncates text to first N chars + mask indicator.',
                'what_it_does' => 'Shows first 20 characters then "[MASKED]" indicator.',
                'how_it_works' => 'Concatenates substring with fixed mask suffix.',
                'sql_block' => <<<SQL
-- Truncate and mask (first 20 chars + indicator)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = CASE
       WHEN LENGTH({{COLUMN}}) > 20
       THEN SUBSTR({{COLUMN}}, 1, 20) || '... [MASKED]'
       ELSE {{COLUMN}}
   END
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Preserves short values unchanged. Shows partial context.',
            ],
            [
                'name' => 'Deterministic Text Hash',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Replaces text with deterministic hash-based token.',
                'what_it_does' => 'Generates unique, reproducible token from text content.',
                'how_it_works' => 'Uses SHA-256 hash truncated to readable length.',
                'sql_block' => <<<SQL
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
                'name' => 'Clear CLOB Content',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Replaces CLOB content with placeholder indicator.',
                'what_it_does' => 'Clears large text objects with "[CLOB CONTENT REMOVED]".',
                'how_it_works' => 'Simple replacement for CLOB columns.',
                'sql_block' => <<<SQL
-- CLOB content replacement
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = TO_CLOB('[CLOB CONTENT REMOVED FOR ANONYMIZATION]')
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Use for CLOB columns. Preserves non-null status.',
            ],
            [
                'name' => 'Pattern-Based PII Redaction (Email)',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Redacts email addresses found within free text.',
                'what_it_does' => 'Finds and replaces email patterns in text with [EMAIL].',
                'how_it_works' => 'Regex pattern matching for email-like strings.',
                'sql_block' => <<<SQL
-- Email pattern redaction in free text
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = REGEXP_REPLACE(
       {{COLUMN}},
       '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}',
       '[EMAIL]'
   )
 WHERE {{COLUMN}} IS NOT NULL
   AND REGEXP_LIKE({{COLUMN}}, '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Scans and replaces all email-like patterns in text.',
            ],
            [
                'name' => 'Pattern-Based PII Redaction (Phone)',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Redacts phone number patterns found within free text.',
                'what_it_does' => 'Finds and replaces phone patterns in text with [PHONE].',
                'how_it_works' => 'Regex pattern matching for phone-like strings.',
                'sql_block' => <<<SQL
-- Phone pattern redaction in free text
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = REGEXP_REPLACE(
       {{COLUMN}},
       '(\+?1[-.\s]?)?(\(?\d{3}\)?[-.\s]?)?\d{3}[-.\s]?\d{4}',
       '[PHONE]'
   )
 WHERE {{COLUMN}} IS NOT NULL
   AND REGEXP_LIKE({{COLUMN}}, '(\+?1[-.\s]?)?(\(?\d{3}\)?[-.\s]?)?\d{3}[-.\s]?\d{4}');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Handles various North American phone formats.',
            ],
            [
                'name' => 'Pattern-Based PII Redaction (SIN)',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Redacts SIN patterns found within free text.',
                'what_it_does' => 'Finds and replaces SIN-like patterns with [SIN].',
                'how_it_works' => 'Regex pattern matching for XXX-XX-XXXX format.',
                'sql_block' => <<<SQL
-- SIN pattern redaction in free text
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = REGEXP_REPLACE(
       {{COLUMN}},
       '\d{3}[-.\s]?\d{2}[-.\s]?\d{4}',
       '[SIN]'
   )
 WHERE {{COLUMN}} IS NOT NULL
   AND REGEXP_LIKE({{COLUMN}}, '\d{3}[-.\s]?\d{2}[-.\s]?\d{4}');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'May produce false positives with 9-digit number sequences.',
            ],
        ];
    }

    /**
     * Numeric perturbation, rounding, and transformation methods.
     */
    protected function numericMethods(): array
    {
        return [
            [
                'name' => 'Numeric Rounding (Integer)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Rounds numeric values to nearest integer.',
                'what_it_does' => 'Removes decimal precision from floating-point values.',
                'how_it_works' => 'Standard rounding to zero decimal places.',
                'sql_block' => <<<SQL
-- Round to integer
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ROUND({{COLUMN}})
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Simple precision reduction. Non-deterministic across jobs.',
            ],
            [
                'name' => 'Numeric Rounding (Nearest 10)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Rounds numeric values to nearest 10.',
                'what_it_does' => 'Generalizes numbers to nearest decade value.',
                'how_it_works' => 'Rounds to -1 decimal places (tens position).',
                'sql_block' => <<<SQL
-- Round to nearest 10
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ROUND({{COLUMN}}, -1)
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Example: 47 → 50, 123 → 120.',
            ],
            [
                'name' => 'Numeric Rounding (Nearest 100)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Rounds numeric values to nearest 100.',
                'what_it_does' => 'Generalizes numbers to nearest hundred.',
                'how_it_works' => 'Rounds to -2 decimal places (hundreds position).',
                'sql_block' => <<<SQL
-- Round to nearest 100
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = ROUND({{COLUMN}}, -2)
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Example: 1234 → 1200, 4567 → 4600.',
            ],
            [
                'name' => 'Numeric Noise Addition (±5%)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Adds random noise within ±5% of original value.',
                'what_it_does' => 'Perturbs numeric values while preserving approximate magnitude.',
                'how_it_works' => 'Multiplies by random factor between 0.95 and 1.05.',
                'sql_block' => <<<SQL
-- Numeric noise ±5%
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = {{COLUMN}} * (0.95 + DBMS_RANDOM.VALUE * 0.10)
 WHERE {{COLUMN}} IS NOT NULL
   AND {{COLUMN}} != 0;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic (random). Preserves sign and approximate scale.',
            ],
            [
                'name' => 'Numeric Noise Addition (±10%)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Adds random noise within ±10% of original value.',
                'what_it_does' => 'Perturbs numeric values with moderate randomization.',
                'how_it_works' => 'Multiplies by random factor between 0.90 and 1.10.',
                'sql_block' => <<<SQL
-- Numeric noise ±10%
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = {{COLUMN}} * (0.90 + DBMS_RANDOM.VALUE * 0.20)
 WHERE {{COLUMN}} IS NOT NULL
   AND {{COLUMN}} != 0;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic (random). Greater perturbation than ±5%.',
            ],
            [
                'name' => 'Numeric Noise (Deterministic ±10%)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING, AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Adds deterministic noise within ±10% based on seed.',
                'what_it_does' => 'Perturbs numeric values reproducibly using hash-based offset.',
                'how_it_works' => 'Uses hash of seed+value to generate consistent perturbation factor.',
                'sql_block' => <<<SQL
-- Deterministic numeric noise ±10%
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = {{COLUMN}} * (
       0.90 + (MOD(ORA_HASH({{JOB_SEED_LITERAL}} || TO_CHAR({{SEED_EXPR}}) || TO_CHAR({{COLUMN}})), 1000) / 5000.0)
   )
 WHERE {{COLUMN}} IS NOT NULL
   AND {{COLUMN}} != 0;
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Deterministic perturbation. Same seed = same noise factor.',
            ],
            [
                'name' => 'Numeric Floor (Remove Decimals)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Floors numeric values, removing decimal portion.',
                'what_it_does' => 'Truncates decimals by flooring toward negative infinity.',
                'how_it_works' => 'Applies FLOOR function to numeric column.',
                'sql_block' => <<<SQL
-- Floor to integer (truncate decimals)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = FLOOR({{COLUMN}})
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Example: 3.7 → 3, -2.3 → -3.',
            ],
            [
                'name' => 'Numeric Range Capping',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Caps extreme values to specified bounds (top/bottom code).',
                'what_it_does' => 'Limits outliers by capping at 1st/99th percentile equivalent.',
                'how_it_works' => 'Uses LEAST/GREATEST to bound values (customize bounds as needed).',
                'sql_block' => <<<SQL
-- Numeric range capping (adjust bounds as needed)
-- Default: cap at 0-999999
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = GREATEST(LEAST({{COLUMN}}, 999999), 0)
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Customize bound values (0, 999999) per column requirements.',
            ],
            [
                'name' => 'Replace with Statistical Surrogate',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Replaces values with table-level average/median.',
                'what_it_does' => 'Eliminates individual variation by using aggregate.',
                'how_it_works' => 'Computes average and applies uniformly (K-anonymity support).',
                'sql_block' => <<<SQL
-- Replace with table average
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = (
       SELECT ROUND(AVG(src.{{COLUMN}}), 2)
       FROM {{TABLE}} src
       WHERE src.{{COLUMN}} IS NOT NULL
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'All rows get same value. High privacy, low utility.',
            ],
        ];
    }

    /**
     * Conditional and rule-based masking methods.
     */
    protected function conditionalMethods(): array
    {
        return [
            [
                'name' => 'Conditional Mask (If Length > N)',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Masks only values exceeding specified length.',
                'what_it_does' => 'Preserves short values, masks longer ones.',
                'how_it_works' => 'Conditional UPDATE based on LENGTH check (default: 10).',
                'sql_block' => <<<SQL
-- Conditional mask for long values (>10 chars)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = CASE
       WHEN LENGTH({{COLUMN}}) > 10
       THEN SUBSTR({{COLUMN}}, 1, 3) || '***MASKED***'
       ELSE {{COLUMN}}
   END
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Customize length threshold (10) as needed.',
            ],
            [
                'name' => 'Conditional Mask (Pattern Match)',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Masks values matching specified pattern only.',
                'what_it_does' => 'Selectively masks rows matching regex criteria.',
                'how_it_works' => 'UPDATE with REGEXP_LIKE filter (customize pattern as needed).',
                'sql_block' => <<<SQL
-- Conditional mask for specific pattern (customize REGEXP)
-- Example: mask values containing digits
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = '[PATTERN_MASKED]'
 WHERE {{COLUMN}} IS NOT NULL
   AND REGEXP_LIKE({{COLUMN}}, '\d');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Customize REGEXP pattern per use case.',
            ],
            [
                'name' => 'Suppress Low-Frequency Values',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Replaces values occurring fewer than N times.',
                'what_it_does' => 'Protects rare values by replacing with "OTHER" (K-anonymity support).',
                'how_it_works' => 'Counts value frequency, suppresses those below threshold.',
                'sql_block' => <<<SQL
-- Suppress low-frequency values (threshold: 5 occurrences)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = 'OTHER'
 WHERE {{COLUMN}} IS NOT NULL
   AND {{COLUMN}} IN (
       SELECT {{COLUMN}}
       FROM {{TABLE}}
       WHERE {{COLUMN}} IS NOT NULL
       GROUP BY {{COLUMN}}
       HAVING COUNT(*) < 5
   );
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Customize threshold (5) for K-anonymity requirements.',
            ],
            [
                'name' => 'Age-Based Date Suppression',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Nullifies dates for records older than threshold.',
                'what_it_does' => 'Suppresses very old dates (HIPAA: >89 years triggers special handling).',
                'how_it_works' => 'Conditional NULL for dates beyond threshold.',
                'sql_block' => <<<SQL
-- Suppress dates for very old records (>89 years per HIPAA)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = NULL
 WHERE {{COLUMN}} IS NOT NULL
   AND {{COLUMN}} < ADD_MONTHS(SYSDATE, -89 * 12);
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'HIPAA safe harbor: ages >89 must be aggregated to "90+".',
            ],
            [
                'name' => 'Preserve Value If In Allowlist',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Masks values unless they appear in allowlist.',
                'what_it_does' => 'Keeps specific safe values unchanged, masks others.',
                'how_it_works' => 'Conditional masking with IN clause for safe values.',
                'sql_block' => <<<SQL
-- Preserve allowlisted values (customize list)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = '[MASKED]'
 WHERE {{COLUMN}} IS NOT NULL
   AND {{COLUMN}} NOT IN ('Active', 'Inactive', 'Pending', 'Closed');
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Customize allowlist values per column domain.',
            ],
        ];
    }

    /**
     * Data shuffling methods for intra-table anonymization.
     */
    protected function shuffleMethods(): array
    {
        return [
            [
                'name' => 'Intra-Column Value Shuffle',
                'category' => AnonymizationMethods::CATEGORY_SHUFFLE_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_SHUFFLE_MASKING],
                'description' => 'Shuffles values within the same column across rows.',
                'what_it_does' => 'Randomly redistributes existing values, preserving overall distribution.',
                'how_it_works' => 'Uses ROWNUM-based random assignment via self-join.',
                'sql_block' => <<<SQL
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
                'name' => 'Group-Based Shuffle',
                'category' => AnonymizationMethods::CATEGORY_SHUFFLE_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_SHUFFLE_MASKING],
                'description' => 'Shuffles values within groups (e.g., same department).',
                'what_it_does' => 'Randomizes within partitions to maintain group-level statistics.',
                'how_it_works' => 'PARTITION BY clause limits shuffle scope (customize partition column).',
                'sql_block' => <<<SQL
-- Group-based shuffle (customize GROUP_COLUMN)
-- Example: shuffle names within same department
MERGE INTO {{TABLE}} tgt
USING (
    SELECT
        ROWID AS rid,
        FIRST_VALUE({{COLUMN}}) OVER (
            PARTITION BY GROUP_COLUMN  -- CUSTOMIZE THIS
            ORDER BY DBMS_RANDOM.VALUE
        ) AS new_val
    FROM {{TABLE}}
    WHERE {{COLUMN}} IS NOT NULL
) src
ON (tgt.ROWID = src.rid)
WHEN MATCHED THEN UPDATE SET tgt.{{COLUMN}} = src.new_val;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Replace GROUP_COLUMN with actual partition column name.',
            ],
            [
                'name' => 'Deterministic Shuffle (Reproducible)',
                'category' => AnonymizationMethods::CATEGORY_SHUFFLE_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_SHUFFLE_MASKING, AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Shuffles values deterministically using hash-based ordering.',
                'what_it_does' => 'Reproducible shuffle: same seed always produces same arrangement.',
                'how_it_works' => 'Uses ORA_HASH with job seed for deterministic reordering.',
                'sql_block' => <<<SQL
-- Deterministic shuffle using hash-based ordering
MERGE INTO {{TABLE}} tgt
USING (
    SELECT
        ROWID AS rid,
        FIRST_VALUE({{COLUMN}}) OVER (
            ORDER BY ORA_HASH({{JOB_SEED_LITERAL}} || ROWID)
        ) AS new_val
    FROM {{TABLE}}
    WHERE {{COLUMN}} IS NOT NULL
) src
ON (tgt.ROWID = src.rid)
WHEN MATCHED THEN UPDATE SET tgt.{{COLUMN}} = src.new_val;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Deterministic: same job seed produces identical shuffle.',
            ],
        ];
    }

    /**
     * Temporal data masking methods.
     */
    protected function temporalMethods(): array
    {
        return [
            [
                'name' => 'Timestamp Jitter (±1 Hour)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Adds random jitter of ±1 hour to timestamps.',
                'what_it_does' => 'Obscures exact time while preserving approximate timing.',
                'how_it_works' => 'Adds random interval between -1 and +1 hours.',
                'sql_block' => <<<SQL
-- Timestamp jitter ±1 hour
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = {{COLUMN}} + NUMTODSINTERVAL((DBMS_RANDOM.VALUE * 2 - 1) * 60, 'MINUTE')
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic. For TIMESTAMP columns.',
            ],
            [
                'name' => 'Date Shift (±30 Days)',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Shifts dates by random offset within ±30 days.',
                'what_it_does' => 'Perturbs dates while preserving seasonal patterns.',
                'how_it_works' => 'Adds random integer between -30 and +30.',
                'sql_block' => <<<SQL
-- Date shift ±30 days (random)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = {{COLUMN}} + TRUNC(DBMS_RANDOM.VALUE * 61) - 30
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Non-deterministic. For DATE columns.',
            ],
            [
                'name' => 'Date Shift (±90 Days, Deterministic)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING, AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Deterministically shifts dates by up to ±90 days.',
                'what_it_does' => 'Reproducible date perturbation for consistency across runs.',
                'how_it_works' => 'Uses hash to generate consistent offset per record.',
                'sql_block' => <<<SQL
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
                'seed_notes' => 'Requires seed. Same seed+value = same offset.',
            ],
            [
                'name' => 'Timestamp Truncate to Hour',
                'category' => AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION,
                'categories' => [AnonymizationMethods::CATEGORY_BLURRING_PERTURBATION],
                'description' => 'Truncates timestamps to the hour boundary.',
                'what_it_does' => 'Removes minute/second precision from timestamps.',
                'how_it_works' => 'Truncates to HH (hour) precision.',
                'sql_block' => <<<SQL
-- Truncate timestamp to hour
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = TRUNC({{COLUMN}}, 'HH')
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Example: 2024-01-15 14:32:45 → 2024-01-15 14:00:00.',
            ],
            [
                'name' => 'Future Date Constraint',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Caps future dates to current date.',
                'what_it_does' => 'Prevents test data from having unrealistic future dates.',
                'how_it_works' => 'Replaces dates beyond SYSDATE with SYSDATE.',
                'sql_block' => <<<SQL
-- Cap future dates to today
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = SYSDATE
 WHERE {{COLUMN}} IS NOT NULL
   AND {{COLUMN}} > SYSDATE;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Useful for cleaning synthetic data or test environments.',
            ],
            [
                'name' => 'Date Relative Preservation',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Shifts all dates by same offset to preserve relative ordering.',
                'what_it_does' => 'Maintains date intervals/sequences while anonymizing.',
                'how_it_works' => 'Calculates offset from reference date, applies uniformly.',
                'sql_block' => <<<SQL
-- Relative date preservation (all dates shifted by same offset)
-- Shift all dates to make earliest date = Jan 1, 2020
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = {{COLUMN}} - (
       SELECT MIN({{COLUMN}}) - DATE '2020-01-01' FROM {{TABLE}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Preserves date intervals. Customize target epoch as needed.',
            ],
        ];
    }

    /**
     * Referential integrity and cascade methods.
     */
    protected function referentialIntegrityMethods(): array
    {
        return [
            [
                'name' => 'FK Cascade (Pre-Update Lookup)',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Updates FK columns by looking up transformed parent values.',
                'what_it_does' => 'Ensures FK references remain valid after parent column is masked.',
                'how_it_works' => 'Joins to seed map table to find new value for parent reference.',
                'sql_block' => <<<SQL
-- FK cascade via seed map lookup
-- Assumes seed_map_{{PARENT_TABLE}} exists with old_value, new_value columns
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = (
       SELECT sm.new_value
       FROM seed_map_{{PARENT_TABLE}} sm
       WHERE sm.old_value = tgt.{{COLUMN}}
   )
 WHERE {{COLUMN}} IS NOT NULL
   AND EXISTS (
       SELECT 1 FROM seed_map_{{PARENT_TABLE}} sm
       WHERE sm.old_value = tgt.{{COLUMN}}
   );
SQL,
                'emits_seed' => false,
                'requires_seed' => true,
                'supports_composite_seed' => false,
                'seed_notes' => 'Requires seed map table from parent anonymization. Replace {{PARENT_TABLE}}.',
            ],
            [
                'name' => 'Orphan Row Suppression',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Nullifies FK values that no longer have valid parent reference.',
                'what_it_does' => 'Cleans up broken references after parent table anonymization.',
                'how_it_works' => 'Sets FK to NULL where parent lookup fails.',
                'sql_block' => <<<SQL
-- Nullify orphan FK references
-- Customize PARENT_TABLE and PARENT_COLUMN
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = NULL
 WHERE {{COLUMN}} IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM PARENT_TABLE p
       WHERE p.PARENT_COLUMN = tgt.{{COLUMN}}
   );
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Replace PARENT_TABLE and PARENT_COLUMN. Use after parent masking.',
            ],
            [
                'name' => 'Preserve NULL Semantics',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Wrapper method ensuring NULL values stay NULL after masking.',
                'what_it_does' => 'Explicitly preserves NULL/non-NULL distinction.',
                'how_it_works' => 'No-op method documenting NULL preservation requirement.',
                'sql_block' => <<<SQL
-- NULL semantics preservation (documentation method)
-- All anonymization methods should include WHERE {{COLUMN}} IS NOT NULL
-- This method exists to document the requirement explicitly.
-- No actual update performed.
SELECT 'NULL preservation verified' FROM DUAL;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Documentation method. Actual NULL handling is in other methods.',
            ],
            [
                'name' => 'Composite Key Hash',
                'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING],
                'description' => 'Generates deterministic surrogate from multiple columns.',
                'what_it_does' => 'Creates compound key replacement when PK spans multiple columns.',
                'how_it_works' => 'Concatenates columns with delimiter, hashes result.',
                'sql_block' => <<<SQL
-- Composite key hash (customize COL1, COL2, etc.)
UPDATE {{TABLE}} tgt
   SET {{COLUMN}} = SUBSTR(
       LOWER(RAWTOHEX(STANDARD_HASH(
           {{JOB_SEED_LITERAL}} || '|' ||
           NVL(TO_CHAR(COL1), 'NULL') || '|' ||
           NVL(TO_CHAR(COL2), 'NULL'),
           'SHA256'
       ))),
       1,
       {{COLUMN_MAX_LEN_EXPR}}
   )
 WHERE {{COLUMN}} IS NOT NULL;
SQL,
                'emits_seed' => true,
                'requires_seed' => false,
                'supports_composite_seed' => true,
                'seed_notes' => 'Replace COL1, COL2 with actual composite key columns.',
            ],
        ];
    }
}
