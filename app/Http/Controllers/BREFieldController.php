<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BREField;
use App\Http\Resources\BREFieldResource;
use Illuminate\Support\Facades\Validator;

class BREFieldController extends Controller
{
    public function index()
    {
        return BREFieldResource::collection(BREField::all());
    }

    public function show($id)
    {
        $breField = BREField::findOrFail($id);
        return new BREFieldResource($breField);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'help_text' => 'nullable|string|max:1000',
            'data_type_id' => 'required|integer|exists:bre_data_types,id',
            'description' => 'nullable|string',
            'field_groups' => 'nullable|array',
            'rule_inputs' => 'nullable|array',
            'rule_outputs' => 'nullable|array',
            'icmcdw_fields' => 'nullable|array',
        ]);

        $breField = BREField::create($validated);

        if (isset($validated['field_groups'])) {
            $breField->syncFieldGroups($validated['field_groups']);
        }

        if (isset($validated['rule_inputs'])) {
            $breField->syncRuleInputs($validated['rule_inputs']);
        }

        if (isset($validated['rule_outputs'])) {
            $breField->syncRuleOutputs($validated['rule_outputs']);
        }

        if (isset($validated['icmcdw_fields'])) {
            $breField->syncIcmCDWFields($validated['icmcdw_fields']);
        }

        return new BREFieldResource($breField);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'help_text' => 'nullable|string|max:1000',
            'data_type_id' => 'required|integer|exists:bre_data_types,id',
            'description' => 'nullable|string',
            'field_groups' => 'nullable|array',
            'rule_inputs' => 'nullable|array',
            'rule_outputs' => 'nullable|array',
            'icmcdw_fields' => 'nullable|array',
        ]);

        $breField = BREField::findOrFail($id);

        $breField->update($validated);

        if (isset($validated['field_groups'])) {
            $breField->syncFieldGroups($validated['field_groups']);
        }

        if (isset($validated['rule_inputs'])) {
            $breField->syncRuleInputs($validated['rule_inputs']);
        }

        if (isset($validated['rule_outputs'])) {
            $breField->syncRuleOutputs($validated['rule_outputs']);
        }

        if (isset($validated['icmcdw_fields'])) {
            $breField->syncIcmCDWFields($validated['icmcdw_fields']);
        }

        return new BREFieldResource($breField);
    }

    public function destroy($id)
    {
        $breField = BREField::findOrFail($id);
        $breField->delete();

        return response()->json(null, 204);
    }
}