<?php

namespace App\Http\Controllers;

use App\Models\BREDataValidation;
use Illuminate\Http\Request;
use App\Http\Resources\BREDataValidationResource;


class BREDataValidationController extends Controller
{
    public function index(Request $request)
    {
        $query = BREDataValidation::query();

        if ($request->has('name')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->name) . '%']);
        }
        if ($request->has('validation_type_id')) {
            $query->where('validation_type_id', $request->validation_type_id);
        }

        if ($request->has('description')) {
            $query->whereRaw('LOWER(description) LIKE ?', ['%' . strtolower($request->description) . '%']);
        }

        if ($request->has('validation_criteria')) {
            $query->whereRaw('LOWER(validation_criteria) LIKE ?', ['%' . strtolower($request->validation_criteria) . '%']);
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
                    ->orWhereRaw('LOWER(validation_criteria) LIKE ?', ['%' . $searchTerm . '%']);
            });
        }

        $breDataValidations = $query->get();
        return BREDataValidationResource::collection($breDataValidations);
    }

    public function show($id)
    {
        $breDataValidation = BREDataValidation::findOrFail($id);
        return new BREDataValidationResource($breDataValidation);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'validation_criteria' => 'nullable|string|max:250',
            'validation_type_id' => 'nullable|integer|exists:bre_validation_types,id',
        ]);

        $breDataValidation = BREDataValidation::create($validated);

        return new BREDataValidationResource($breDataValidation);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'validation_criteria' => 'nullable|string|max:250',
            'validation_type_id' => 'nullable|integer|exists:bre_validation_types,id',
        ]);

        $breDataValidation = BREDataValidation::findOrFail($id);

        $breDataValidation->update($validated);

        return new BREDataValidationResource($breDataValidation);
    }

    public function destroy($id)
    {
        $breDataValidation = BREDataValidation::findOrFail($id);
        $breDataValidation->delete();

        return response()->json(null, 204);
    }
}
