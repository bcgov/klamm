<?php

namespace Database\Seeders;

use App\Models\Anonymizer\AnonymizationPackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AnonymizationPackageSeeder extends Seeder
{
    public function run(): void
    {
        // Intentionally empty.
        // Packages can contain vendor- or environment-specific SQL/PLSQL.
        // We do not ship or seed any default packages in Klamm.
    }

    protected function seedPackageFromDir(
        string $handle,
        string $name,
        string $packageName,
        string $summary,
        string $dir,
        string $installFile,
        string $specFile,
        string $bodyFile,
    ): void {
        if (! File::isDirectory($dir)) {
            $this->command?->warn("Package directory missing; skipping seed: {$handle}");
            return;
        }

        $installSql = $this->loadSql($dir . '/' . $installFile);
        $specSql = $this->loadSql($dir . '/' . $specFile);
        $bodySql = $this->loadSql($dir . '/' . $bodyFile);

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

        if (method_exists($package, 'trashed') && $package->trashed()) {
            $package->restore();
        }
    }

    protected function loadSql(string $path): string
    {
        if (! File::exists($path)) {
            $this->command?->warn('Missing SQL artifact: ' . Str::after($path, base_path()));
            return '';
        }

        return trim(File::get($path));
    }
}
