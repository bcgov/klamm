<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BREValueType;
use App\Http\Resources\BREValueTypeResource;
use Illuminate\Support\Facades\Validator;

class BREValueTypeController extends Controller
{
    public function index()
    {
        return BREValueTypeResource::collection(BREValueType::all());
    }

    public function show($id)
    {
        $breValueType = BREValueType::findOrFail($id);
        return new BREValueTypeResource($breValueType);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);
        $breValueType = BREValueType::create($validated);

        return new BREValueTypeResource($breValueType);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $breValueType = BREValueType::findOrFail($id);

        $breValueType->update($validated);

        return new BREValueTypeResource($breValueType);
    }

    public function destroy($id)
    {
        $breField = BREValueType::findOrFail($id);
        $breField->delete();

        return response()->json(null, 204);
    }
}
