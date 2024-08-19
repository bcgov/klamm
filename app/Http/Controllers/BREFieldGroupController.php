<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BREFieldGroup;
use App\Http\Resources\BREFieldGroupResource;
use Illuminate\Support\Facades\Validator;

class BREFieldGroupController extends Controller
{
    public function index()
    {
        return BREFieldGroupResource::collection(BREFieldGroup::all());
    }

    public function show($id)
    {
        $breFieldGroup = BREFieldGroup::findOrFail($id);
        return new BREFieldGroupResource($breFieldGroup);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'internal_description' => 'nullable|string|max:1000',
            'bre_fields' => 'array',
            'bre_fields.*.id' => 'integer|exists:bre_fields,id',
        ]);

        $breFieldGroup = BREFieldGroup::create($validated);

        if (isset($validated['bre_fields'])) {
            $breFieldGroup->syncbreFields($validated['bre_fields']);
        }

        return new BREFieldGroupResource($breFieldGroup);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'internal_description' => 'nullable|string|max:1000',
            'bre_fields' => 'array',
            'bre_fields.*.id' => 'integer|exists:bre_fields,id',
        ]);

        $breFieldGroup = BREFieldGroup::findOrFail($id);

        $breFieldGroup->update($validated);

        if (isset($validated['bre_fields'])) {
            $breFieldGroup->syncbreFields($validated['bre_fields']);
        }


        return new BREFieldGroupResource($breFieldGroup);
    }

    public function destroy($id)
    {
        $breField = BREFieldGroup::findOrFail($id);
        $breField->delete();

        return response()->json(null, 204);
    }
}
