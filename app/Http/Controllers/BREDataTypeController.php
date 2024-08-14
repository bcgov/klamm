<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BREDataType;
use App\Http\Resources\BREDataTypeResource;
use Illuminate\Support\Facades\Validator;

class BREDataTypeController extends Controller
{
    public function index()
    {
        return BREDataTypeResource::collection(BREDataType::all());
    }

    public function show($id)
    {
        $breDataType = BREDataType::findOrFail($id);
        return new BREDataTypeResource($breDataType);
    }

    public function store(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_description' => 'nullable|string|max:255',
            'long_description' => 'nullable|string',
            'value_type_id' => 'nullable|integer|exists:bre_value_types,id',
        ]);

        $breDataType = BREDataType::create($validated);

        return new BREDataTypeResource($breDataType);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'value_type_id' => 'nullable|integer|exists:bre_value_types,id',
        ]);

        $breDataType = BREDataType::findOrFail($id);

        $breDataType->update($validated);

        return new BREDataTypeResource($breDataType);
    }

    public function destroy($id)
    {
        $breField = BREDataType::findOrFail($id);
        $breField->delete();

        return response()->json(null, 204);
    }
}
