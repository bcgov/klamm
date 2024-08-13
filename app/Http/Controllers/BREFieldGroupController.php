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
        // Validation code
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'internal_description' => 'nullable|string|max:1000',
        ]);

        // Create a new BREField
        $breFieldGroup = BREFieldGroup::create($validated);

        return new BREFieldGroupResource($breFieldGroup);
    }

    public function update(Request $request, $id)
    {
        // Validation code
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'internal_description' => 'nullable|string|max:1000',
        ]);

        // Find the BREField by ID
        $breFieldGroup = BREFieldGroup::findOrFail($id);

        // Update the BREField
        $breFieldGroup->update($validated);

        return new BREFieldGroupResource($breFieldGroup);
    }

    public function destroy($id)
    {
        $breField = BREFieldGroup::findOrFail($id);
        $breField->delete();

        return response()->json(null, 204);
    }
}
