<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ICMCDWField;
use App\Http\Resources\ICMCDWFieldResource;
use Illuminate\Support\Facades\Validator;

class ICMCDWFieldController extends Controller
{
    public function index()
    {
        return ICMCDWFieldResource::collection(ICMCDWField::all());
    }

    public function show($id)
    {
        $icmCDWField = ICMCDWField::findOrFail($id);
        return new ICMCDWFieldResource($icmCDWField);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'field' => 'nullable|string|max:255',
            'panel_type' => 'nullable|string|max:255',
            'entity' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:255',
            'subject_area' => 'nullable|string|max:255',
            'applet' => 'nullable|string|max:255',
            'datatype' => 'nullable|string|max:255',
            'field_input_max_length' => 'nullable|string|max:255',
            'ministry' => 'nullable|string|max:255',
            'cdw_ui_caption' => 'nullable|string|max:255',
            'cdw_table_name' => 'nullable|string|max:255',
            'cdw_column_name' => 'nullable|string|max:255',
            'bre_fields' => 'array',
            'bre_fields.*.id' => 'integer|exists:bre_fields,id',
        ]);

        $icmCDWField = ICMCDWField::create($validated);

        if (isset($validated['bre_fields'])) {
            $icmCDWField->syncBreFields($validated['bre_fields']);
        }

        return new ICMCDWFieldResource($icmCDWField);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'field' => 'nullable|string|max:255',
            'panel_type' => 'nullable|string|max:255',
            'entity' => 'nullable|string|max:255',
            'path' => 'nullable|string|max:255',
            'subject_area' => 'nullable|string|max:255',
            'applet' => 'nullable|string|max:255',
            'datatype' => 'nullable|string|max:255',
            'field_input_max_length' => 'nullable|string|max:255',
            'ministry' => 'nullable|string|max:255',
            'cdw_ui_caption' => 'nullable|string|max:255',
            'cdw_table_name' => 'nullable|string|max:255',
            'cdw_column_name' => 'nullable|string|max:255',
            'bre_fields' => 'array',
            'bre_fields.*.id' => 'integer|exists:bre_fields,id',
        ]);

        $icmCDWField = ICMCDWField::findOrFail($id);

        $icmCDWField->update($validated);

        if (isset($validated['bre_fields'])) {
            $icmCDWField->syncBreFields($validated['bre_fields']);
        }

        return new ICMCDWFieldResource($icmCDWField);
    }

    public function destroy($id)
    {
        $icmCDWField = ICMCDWField::findOrFail($id);
        $icmCDWField->delete();

        return response()->json(null, 204);
    }
}
