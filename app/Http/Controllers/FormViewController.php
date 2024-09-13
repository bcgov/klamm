<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RenderedForm;

class FormViewController extends Controller
{
    public function show($uuid)
    {
        $form = RenderedForm::where('id', $uuid)->firstOrFail();
        return view('rendered_forms.view', compact('form'));
    }

    public function preview(Request $request)
    {
        $encodedJson = $request->query('json', '');
        $jsonStructure = base64_decode($encodedJson);

        return view('rendered_forms.preview', ['jsonStructure' => $jsonStructure]);
    }
}
