<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BREFieldGroup;
use App\Http\Resources\BREFieldGroupResource;
use App\Http\Resources\BREFieldResource;
use Illuminate\Support\Facades\Validator;

class BREFieldGroupController extends Controller
{
    public function index(Request $request)
    {
        $query = BREFieldGroup::query();

        $query->with([
            'breFields',
            'breFields.breDataType.breValueType',
            'breFields.breDataValidation.breValidationType',
            'breFields.breInputs',
            'breFields.breOutputs'
        ]);

        if ($request->has('name')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->name) . '%']);
        }

        if ($request->has('label')) {
            $query->whereRaw('LOWER(label) LIKE ?', ['%' . strtolower($request->label) . '%']);
        }

        if ($request->has('description')) {
            $query->whereRaw('LOWER(description) LIKE ?', ['%' . strtolower($request->description) . '%']);
        }

        if ($request->has('internal_description')) {
            $query->whereRaw('LOWER(internal_description) LIKE ?', ['%' . strtolower($request->internal_description) . '%']);
        }

        if ($request->has('bre_fields')) {
            $query->whereHas('breFields');
        }

        if ($request->has('bre_fields_name')) {
            $query->whereHas('breFields', function ($query) use ($request) {
                $query->where('name', $request->bre_fields_name);
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
                    ->orWhereRaw('LOWER(label) LIKE ?', ['%' . $searchTerm . '%'])
                    ->orWhereRaw('LOWER(description) LIKE ?', ['%' . $searchTerm . '%'])
                    ->orWhereRaw('LOWER(internal_description) LIKE ?', ['%' . $searchTerm . '%']);
            });
        }

        $breFieldGroups = $query->get();
        return BREFieldGroupResource::collection($breFieldGroups);
    }

    public function show($id)
    {
        $query = BREFieldGroup::with([
            'breFields',
            'breFields.breDataType.breValueType',
            'breFields.breDataValidation.breValidationType',
            'breFields.breInputs',
            'breFields.breOutputs'
        ]);

        if (is_numeric($id)) {
            $breFieldGroup = $query->findOrFail($id);
        } else {
            $breFieldGroup = $query->where('name', $id)->firstOrFail();
        }

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
