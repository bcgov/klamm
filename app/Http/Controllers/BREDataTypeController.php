<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BREDataType;
use App\Http\Resources\BREDataTypeResource;
use Illuminate\Support\Facades\Validator;

class BREDataTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = BREDataType::query();

        if ($request->has('name')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->name) . '%']);
        }
        if ($request->has('value_type_id')) {
            $query->where('value_type_id', $request->value_type_id);
        }

        if ($request->has('short_description')) {
            $query->whereRaw('LOWER(short_description) LIKE ?', ['%' . strtolower($request->short_description) . '%']);
        }

        if ($request->has('long_description')) {
            $query->whereRaw('LOWER(long_description) LIKE ?', ['%' . strtolower($request->long_description) . '%']);
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
                    ->orWhereRaw('LOWER(short_description) LIKE ?', ['%' . $searchTerm . '%'])
                    ->orWhereRaw('LOWER(long_description) LIKE ?', ['%' . $searchTerm . '%']);
            });
        }

        $breDataTypes = $query->get();
        return BREDataTypeResource::collection($breDataTypes);
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
