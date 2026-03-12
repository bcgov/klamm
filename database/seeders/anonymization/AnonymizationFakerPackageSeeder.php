<?php

namespace Database\Seeders\Anonymization;

use App\Models\Anonymizer\AnonymizationPackage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Seeds AnonymizationPackage records from Faker-generated packages.
 *
 * The seeder creates AnonymizationPackage records that reference the generated packages for lookup-based data anonymization.
 * To regenerate the packages run the py script at scripts\oracle\generate_faker_anonymization_packages.py, which outputs SQL files to database/seeders/anonymization/packages.
 */
class AnonymizationFakerPackageSeeder extends AnonymizationPackageSeeder
{
    /**
     * Base directory for generated Faker packages.
     */
    protected const PACKAGES_DIR = 'database/seeders/anonymization/packages';

    /**
     * Package definitions mapping handle to metadata.
     * Each entry defines:
     *   - name: Human-readable name
     *   - package_name: package name (without schema)
     *   - summary: Brief description
     *   - spec_file: Specification SQL filename
     *   - body_file: Body SQL filename
     */
    protected array $packageDefinitions = [
        'anon_first_names' => [
            'name' => 'Faker First Names',
            'package_name' => 'PKG_ANON_FIRST_NAMES',
            'summary' => 'Synthetic first names for PII masking (10,000+ entries)',
        ],
        'anon_last_names' => [
            'name' => 'Faker Last Names',
            'package_name' => 'PKG_ANON_LAST_NAMES',
            'summary' => 'Synthetic last names for PII masking (10,000+ entries)',
        ],
        'anon_full_names' => [
            'name' => 'Faker Full Names',
            'package_name' => 'PKG_ANON_FULL_NAMES',
            'summary' => 'Synthetic full names for PII masking (10,000+ entries)',
        ],
        'anon_emails' => [
            'name' => 'Faker Email Addresses',
            'package_name' => 'PKG_ANON_EMAILS',
            'summary' => 'Synthetic email addresses using safe domains (10,000+ entries)',
        ],
        'anon_phones' => [
            'name' => 'Faker Phone Numbers',
            'package_name' => 'PKG_ANON_PHONES',
            'summary' => 'Synthetic phone numbers using reserved exchanges (10,000+ entries)',
        ],
        'anon_addresses' => [
            'name' => 'Faker Street Addresses',
            'package_name' => 'PKG_ANON_ADDRESSES',
            'summary' => 'Synthetic street addresses for PII masking (10,000+ entries)',
        ],
        'anon_cities' => [
            'name' => 'Faker Cities',
            'package_name' => 'PKG_ANON_CITIES',
            'summary' => 'Synthetic city names for address masking (5,000+ entries)',
        ],
        'anon_states' => [
            'name' => 'Faker States/Provinces',
            'package_name' => 'PKG_ANON_STATES',
            'summary' => 'US states and Canadian provinces for address masking',
        ],
        'anon_postal_codes' => [
            'name' => 'Faker Postal Codes',
            'package_name' => 'PKG_ANON_POSTAL_CODES',
            'summary' => 'Synthetic US ZIP and Canadian postal codes (10,000+ entries)',
        ],
        'anon_companies' => [
            'name' => 'Faker Company Names',
            'package_name' => 'PKG_ANON_COMPANIES',
            'summary' => 'Synthetic company/organization names (5,000+ entries)',
        ],
        'anon_job_titles' => [
            'name' => 'Faker Job Titles',
            'package_name' => 'PKG_ANON_JOB_TITLES',
            'summary' => 'Synthetic job titles for employment data masking (2,000+ entries)',
        ],
        'anon_credit_cards' => [
            'name' => 'Faker Credit Cards (Masked)',
            'package_name' => 'PKG_ANON_CREDIT_CARDS',
            'summary' => 'Non-functional masked credit card surrogates (10,000+ entries)',
        ],
        'anon_sin' => [
            'name' => 'Faker SIN Surrogates',
            'package_name' => 'PKG_ANON_SIN',
            'summary' => 'Invalid SIN-format surrogates for testing (10,000+ entries)',
        ],
        'anon_ssn' => [
            'name' => 'Faker SSN Surrogates',
            'package_name' => 'PKG_ANON_SSN',
            'summary' => 'Invalid SSN-format surrogates for testing (10,000+ entries)',
        ],
        'anon_accounts' => [
            'name' => 'Faker Account Numbers',
            'package_name' => 'PKG_ANON_ACCOUNTS',
            'summary' => 'Synthetic account/reference numbers (10,000+ entries)',
        ],
        'anon_usernames' => [
            'name' => 'Faker Usernames',
            'package_name' => 'PKG_ANON_USERNAMES',
            'summary' => 'Synthetic usernames for login data masking (10,000+ entries)',
        ],
        'anon_ages' => [
            'name' => 'Faker Ages',
            'package_name' => 'PKG_ANON_AGES',
            'summary' => 'Valid age values (18-99) for numeric masking',
        ],
        'anon_statuses' => [
            'name' => 'Siebel Status Values',
            'package_name' => 'PKG_ANON_STATUSES',
            'summary' => 'Standard Siebel-compatible status values',
        ],
        'anon_priorities' => [
            'name' => 'Siebel Priority Values',
            'package_name' => 'PKG_ANON_PRIORITIES',
            'summary' => 'Standard Siebel-compatible priority values',
        ],
        'anon_types' => [
            'name' => 'Siebel Type Values',
            'package_name' => 'PKG_ANON_TYPES',
            'summary' => 'Standard Siebel entity type values',
        ],
        'anon_comments' => [
            'name' => 'Faker Comment Text',
            'package_name' => 'PKG_ANON_COMMENTS',
            'summary' => 'Lorem ipsum placeholder text for notes/comments (5,000+ entries)',
        ],
    ];

    public function run(): void
    {
        $packagesDir = base_path(self::PACKAGES_DIR);

        if (! File::isDirectory($packagesDir)) {
            $this->command?->warn("Expected directory: {$packagesDir}");

            return;
        }

        $seededCount = 0;
        $skippedCount = 0;

        foreach ($this->packageDefinitions as $handle => $definition) {
            $specFile = "{$handle}_spec.sql";
            $bodyFile = "{$handle}_body.sql";

            $specPath = $packagesDir . '/' . $specFile;
            $bodyPath = $packagesDir . '/' . $bodyFile;

            if (! File::exists($specPath) || ! File::exists($bodyPath)) {
                $this->command?->warn("Missing SQL files for {$handle}; skipping.");
                $skippedCount++;

                continue;
            }

            $this->seedFakerPackage(
                handle: $handle,
                name: $definition['name'],
                packageName: $definition['package_name'],
                summary: $definition['summary'],
                specPath: $specPath,
                bodyPath: $bodyPath,
            );

            $seededCount++;
        }

        $this->command?->info("Seeded {$seededCount} Faker anonymization packages.");

        if ($skippedCount > 0) {
            $this->command?->warn("Skipped {$skippedCount} packages (missing SQL files).");
        }
    }

    /**
     * Seed a single Faker package from its generated SQL files.
     */
    protected function seedFakerPackage(
        string $handle,
        string $name,
        string $packageName,
        string $summary,
        string $specPath,
        string $bodyPath,
    ): void {
        $specSql = $this->normalizeFakerPackageSql($this->loadSql($specPath));
        $bodySql = $this->normalizeFakerPackageSql($this->loadSql($bodyPath));

        if (empty($specSql) || empty($bodySql)) {
            $this->command?->warn("Empty SQL content for {$handle}; skipping.");

            return;
        }

        // Generate install SQL that creates both spec and body
        $installSql = $this->generateInstallSql($handle, $packageName);

        $package = AnonymizationPackage::withTrashed()->updateOrCreate(
            ['handle' => $handle],
            [
                'name' => $name,
                'package_name' => $packageName,
                'database_platform' => 'oracle',
                'summary' => $summary,
                'install_sql' => $installSql,
                'package_spec_sql' => $specSql,
                'package_body_sql' => $bodySql,
            ]
        );

        if ($package->trashed()) {
            $package->restore();
        }
    }

    /**
     * Normalize generated package SQL for broader Oracle compatibility.
     *
     * Current faker package generator emits DBMS_CRYPTO hashing in deterministic
     * lookups, which requires EXECUTE on SYS.DBMS_CRYPTO in target schemas.
     * Replace with STANDARD_HASH to avoid that privilege dependency.
     */
    protected function normalizeFakerPackageSql(string $sql): string
    {
        if ($sql === '') {
            return '';
        }

        // Prefer DBMS_UTILITY.GET_HASH_VALUE for widest PL/SQL compatibility.
        // This avoids requiring DBMS_CRYPTO grants and avoids environments
        // where STANDARD_HASH/ORA_HASH are unavailable in PL/SQL contexts.
        $normalized = preg_replace(
            "/v_hash\\s*:=\\s*DBMS_CRYPTO\\.HASH\\(\\s*UTL_RAW\\.CAST_TO_RAW\\(\\s*NVL\\(p_seed,\\s*'NULL'\\)\\s*\\)\\s*,\\s*DBMS_CRYPTO\\.HASH_SH256\\s*\\)\\s*;\\s*v_idx\\s*:=\\s*MOD\\(TO_NUMBER\\(SUBSTR\\(RAWTOHEX\\(v_hash\\),\\s*1,\\s*8\\),\\s*'XXXXXXXX'\\),\\s*C_COUNT\\)\\s*\\+\\s*1\\s*;/is",
            "v_idx := MOD(ABS(DBMS_UTILITY.GET_HASH_VALUE(NVL(p_seed, 'NULL'), 0, 2147483647)), C_COUNT) + 1;",
            $sql
        );

        $normalized = preg_replace(
            "/v_hash\\s*:=\\s*STANDARD_HASH\\(\\s*NVL\\(p_seed,\\s*'NULL'\\)\\s*,\\s*'SHA256'\\s*\\)\\s*;\\s*v_idx\\s*:=\\s*MOD\\(TO_NUMBER\\(SUBSTR\\(RAWTOHEX\\(v_hash\\),\\s*1,\\s*8\\),\\s*'XXXXXXXX'\\),\\s*C_COUNT\\)\\s*\\+\\s*1\\s*;/is",
            "v_idx := MOD(ABS(DBMS_UTILITY.GET_HASH_VALUE(NVL(p_seed, 'NULL'), 0, 2147483647)), C_COUNT) + 1;",
            $normalized ?? $sql
        );

        $normalized = preg_replace(
            "/v_idx\\s*:=\\s*MOD\\(ABS\\(ORA_HASH\\(NVL\\(p_seed,\\s*'NULL'\\),\\s*4294967295\\)\\),\\s*C_COUNT\\)\\s*\\+\\s*1\\s*;/is",
            "v_idx := MOD(ABS(DBMS_UTILITY.GET_HASH_VALUE(NVL(p_seed, 'NULL'), 0, 2147483647)), C_COUNT) + 1;",
            $normalized ?? $sql
        );

        return $normalized ?? $sql;
    }

    /**
     * Generate installation SQL with usage instructions.
     */
    protected function generateInstallSql(string $handle, string $packageName): string
    {
        return <<<SQL
-- Installation script for {$packageName}
-- Handle: {$handle}
-- Platform: Oracle
--
-- This package is self-contained with embedded synthetic data.
--
-- To install:
--   1. Run the Package Specification SQL first
--   2. Run the Package Body SQL second
--   3. Grant EXECUTE to users/schemas that need access
--
-- Example:
--   GRANT EXECUTE ON ANON_DATA.{$packageName} TO target_schema;
--
-- Usage in anonymization SQL:
--   -- Deterministic lookup (consistent for same seed)
--   ANON_DATA.{$packageName}.GET_*(seed_value, max_length)
--
--   -- Random lookup (different each call)
--   ANON_DATA.{$packageName}.GET_*_RANDOM(max_length)
--
--   -- Index-based lookup (1 to N)
--   ANON_DATA.{$packageName}.GET_*_BY_INDEX(index, max_length)
SQL;
    }

    /**
     * Get the list of available package handles.
     */
    public function getAvailablePackages(): array
    {
        return array_keys($this->packageDefinitions);
    }

    /**
     * Check if all expected package files exist.
     */
    public function validatePackageFiles(): array
    {
        $packagesDir = base_path(self::PACKAGES_DIR);
        $results = [];

        foreach ($this->packageDefinitions as $handle => $definition) {
            $specPath = $packagesDir . '/' . $handle . '_spec.sql';
            $bodyPath = $packagesDir . '/' . $handle . '_body.sql';

            $results[$handle] = [
                'name' => $definition['name'],
                'spec_exists' => File::exists($specPath),
                'body_exists' => File::exists($bodyPath),
                'ready' => File::exists($specPath) && File::exists($bodyPath),
            ];
        }

        return $results;
    }
}
