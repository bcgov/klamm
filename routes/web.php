<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormViewController;
use App\Models\Anonymizer\AnonymizationJobs;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Explicit login route (otherwise it only lives in the admin panel)
Route::get('/login', function () {
    return redirect(route('filament.admin.auth.login'));
})->name('login');

Route::get('/', function () {
    return redirect(route('filament.home.pages.welcome'));
});

Route::get('/download/form-json/{filename}', function ($filename) {
    // Validate filename to prevent directory traversal
    if (!preg_match('/^form_[a-zA-Z0-9_\-]+\.json$/', $filename)) {
        abort(404);
    }

    if (!Storage::disk('templates')->exists($filename)) {
        abort(404);
    }

    $fileContents = Storage::disk('templates')->get($filename);

    return response($fileContents)
        ->header('Content-Type', 'application/json')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
})->name('download.form-json');

Route::middleware('auth')->get('/download/anonymization-job-sql/{job}', function (AnonymizationJobs $job) {
    abort_unless(request()->user()?->can('view', $job), 403);

    $jobId = (int) $job->getKey();
    $length = (int) DB::table('anonymization_jobs')
        ->where('id', $jobId)
        ->selectRaw('length(sql_script) as len')
        ->value('len');

    if ($length === 0) {
        abort(404);
    }

    $timestamp = now()->format('Ymd_His');
    $name = Str::slug($job->name) ?: 'anonymization-job';
    $filename = $timestamp . '_' . $name . '.sql';

    return response()->streamDownload(function () use ($jobId) {
        $chunkSize = 5_000_000;
        $offset = 0;

        while (true) {
            $chunk = DB::table('anonymization_jobs')
                ->where('id', $jobId)
                ->selectRaw('substr(sql_script, ?, ?) as chunk', [$offset + 1, $chunkSize])
                ->value('chunk');

            if ($chunk === null || $chunk === '') {
                break;
            }

            echo $chunk;
            flush();
            $offset += $chunkSize;

            if (strlen($chunk) < $chunkSize) {
                break;
            }
        }
    }, $filename, [
        'Content-Type' => 'text/sql; charset=UTF-8',
    ]);
})->name('download.anonymization-job-sql');
