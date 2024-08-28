<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ICMCDWField;
use App\Http\Resources\ICMCDWFieldResource;
use Illuminate\Support\Facades\Validator;

class ICMCDWFieldController extends Controller
{
    public function index(Request $request)
    {
        $query = ICMCDWField::query();

        if ($request->has('name')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->name) . '%']);
        }

        if ($request->has('field')) {
            $query->whereRaw('LOWER(field) LIKE ?', ['%' . strtolower($request->field) . '%']);
        }

        if ($request->has('entity')) {
            $query->whereRaw('LOWER(entity) LIKE ?', ['%' . strtolower($request->entity) . '%']);
        }

        if ($request->has('path')) {
            $query->whereRaw('LOWER(path) LIKE ?', ['%' . strtolower($request->path) . '%']);
        }

        if ($request->has('bre_fields')) {
            $query->whereHas('breFields', function ($query) use ($request) {
                if ($request->filled('bre_fields')) {
                    $input = $request->bre_fields;
                    if (is_numeric($input)) {
                        $query->where('bre_fields.id', $input);
                    } else {
                        $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($input) . '%']);
                    }
                }
            });
        }

        if ($request->has('order_by')) {
            $orderDirection = $request->input('order_direction', 'asc');
            $query->orderBy($request->order_by, $orderDirection);
        }

        if ($request->has('limit')) {
            $query->limit($request->limit);
        }

        if ($request->has('offset')) {
            $query->offset($request->offset);
        }

        if ($request->has('search')) {
            $searchTerm = strtolower($request->search);

            $query->where(function ($query) use ($searchTerm) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . $searchTerm . '%'])
                    ->orWhereRaw('LOWER(field) LIKE ?', ['%' . $searchTerm . '%'])
                    ->orWhereRaw('LOWER(path) LIKE ?', ['%' . $searchTerm . '%'])
                    ->orWhereRaw('LOWER(subject_area) LIKE ?', ['%' . $searchTerm . '%'])
                    ->orWhereRaw('LOWER(applet) LIKE ?', ['%' . $searchTerm . '%'])
                    ->orWhereRaw('LOWER(datatype) LIKE ?', ['%' . $searchTerm . '%'])
                    ->orWhereRaw('LOWER(cdw_ui_caption) LIKE ?', ['%' . $searchTerm . '%'])
                    ->orWhereRaw('LOWER(cdw_table_name) LIKE ?', ['%' . $searchTerm . '%'])
                    ->orWhereRaw('LOWER(cdw_column_name) LIKE ?', ['%' . $searchTerm . '%']);
            });
        }

        $icmcdwFields = $query->get();
        return ICMCDWFieldResource::collection($icmcdwFields);
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
