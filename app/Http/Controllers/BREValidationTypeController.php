<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BREValidationType;
use App\Http\Resources\BREValidationTypeResource;

class BREValidationTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = BREValidationType::query();

        if ($request->has('name')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->name) . '%']);
        }

        if ($request->has('description')) {
            $query->whereRaw('LOWER(description) LIKE ?', ['%' . strtolower($request->description) . '%']);
        }

        if ($request->has('value')) {
            $query->whereRaw('LOWER(value) LIKE ?', ['%' . strtolower($request->value) . '%']);
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
                    ->orWhereRaw('LOWER(description) LIKE ?', ['%' . $searchTerm . '%'])
                    ->orWhereRaw('LOWER(value) LIKE ?', ['%' . $searchTerm . '%']);
            });
        }

        $breValidationTypes = $query->get();
        return BREValidationTypeResource::collection($breValidationTypes);
    }

    public function show($id)
    {
        $BREValidationType = BREValidationType::findOrFail($id);
        return new BREValidationTypeResource($BREValidationType);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'value' => 'nullable|string|max:255',
        ]);
        $BREValidationType = BREValidationType::create($validated);

        return new BREValidationTypeResource($BREValidationType);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'value' => 'nullable|string|max:255',
        ]);

        $BREValidationType = BREValidationType::findOrFail($id);

        $BREValidationType->update($validated);

        return new BREValidationTypeResource($BREValidationType);
    }

    public function destroy($id)
    {
        $breValidationType = BREValidationType::findOrFail($id);
        $breValidationType->delete();

        return response()->json(null, 204);
    }
}
