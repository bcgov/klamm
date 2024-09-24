<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BREValueType;
use App\Http\Resources\BREValueTypeResource;
use Illuminate\Support\Facades\Validator;

class BREValueTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = BREValueType::query();

        if ($request->has('name')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->name) . '%']);
        }

        if ($request->has('description')) {
            $query->whereRaw('LOWER(description) LIKE ?', ['%' . strtolower($request->description) . '%']);
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
                    ->orWhereRaw('LOWER(description) LIKE ?', ['%' . $searchTerm . '%']);
            });
        }

        $breValueTypes = $query->get();
        return BREValueTypeResource::collection($breValueTypes);
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
