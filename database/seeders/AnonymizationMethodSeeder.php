<?php

namespace Database\Seeders;

use App\Models\Anonymizer\AnonymizationMethods;
use Illuminate\Database\Seeder;

class AnonymizationMethodSeeder extends Seeder
{
    public function run(): void
    {
        // Base methods required for anonymization demos and day-to-day usage.
        // SQL-only methods are seeded via AnonymizationSqlOnlyMethodSeeder.
        $this->call([
            AnonymizationSqlOnlyMethodSeeder::class,
        ]);

        $genericMethods = [
            [
                'name' => 'Redact (Fixed Token)',
                'category' => AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING,
                'categories' => [AnonymizationMethods::CATEGORY_CONDITIONAL_MASKING],
                'description' => 'Replaces a value with a fixed redaction token.',
                'what_it_does' => 'Overwrites sensitive values with a constant (e.g., REDACTED) to eliminate leakage risk.',
                'how_it_works' => 'Simple UPDATE that replaces non-null values with a fixed token.',
                'sql_block' => <<<SQL
-- Hard redaction (non-reversible)
update {{TABLE}} tgt
   set {{COLUMN}} = 'REDACTED'
 where {{COLUMN}} is not null;
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
                'what_it_does' => 'Eliminates sensitive data completely in columns where NULL is allowed and downstream logic tolerates it.',
                'how_it_works' => 'Simple UPDATE that sets the column to NULL.',
                'sql_block' => <<<SQL
-- Hard suppression (set NULL)
update {{TABLE}} tgt
   set {{COLUMN}} = null
 where {{COLUMN}} is not null;
SQL,
                'emits_seed' => false,
                'requires_seed' => false,
                'supports_composite_seed' => false,
                'seed_notes' => 'Use only when NULL is allowed and accepted by the application (e.g., optional PII fields).',
            ],
        ];

        foreach ($genericMethods as $payload) {
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
