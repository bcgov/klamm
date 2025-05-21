<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormTemplateController extends Controller
{
    /**
     * Download a generated form template
     */
    public function download($filename)
    {
        $path = 'form_templates/' . $filename;

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'Template file not found');
        }

        $fullPath = Storage::disk('local')->path($path);

        $filenameWithoutUuid = preg_replace('/_[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.json$/i', '', $filename);
        $downloadFilename = $filenameWithoutUuid . '.json';

        return response()->download(
            $fullPath,
            $downloadFilename,
            [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . $downloadFilename . '"'
            ]
        );
    }
}
