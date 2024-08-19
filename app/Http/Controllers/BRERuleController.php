<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BRERule;
use App\Http\Resources\BRERuleResource;
use Illuminate\Support\Facades\Validator;

class BRERuleController extends Controller
{
    public function index()
    {
        return BRERuleResource::collection(BRERule::all());
    }

    public function show($id)
    {
        $breRule = BRERule::findOrFail($id);
        return new BRERuleResource($breRule);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'internal_description' => 'nullable|string|max:1000',
            'rule_inputs' => 'array',
            'rule_inputs.*.id' => 'integer|exists:bre_fields,id',
            'rule_outputs' => 'array',
            'rule_outputs.*.id' => 'integer|exists:bre_fields,id',
            'parent_rules' => 'array',
            'parent_rules.*.id' => 'integer|exists:bre_rules,id',
            'child_rules' => 'array',
            'child_rules.*.id' => 'integer|exists:bre_rules,id',
            'icmcdw_fields' => 'array',
            'icmcdw_fields.*.id' => 'integer|exists:icm_cdw_fields,id',
        ]);

        $breRule = BRERule::create($validated);

        if (isset($validated['rule_inputs'])) {
            $breRule->syncRuleInputs($validated['rule_inputs']);
        }

        if (isset($validated['rule_outputs'])) {
            $breRule->syncRuleOutputs($validated['rule_outputs']);
        }

        if (isset($validated['parent_rules'])) {
            $breRule->syncParentRules($validated['parent_rules']);
        }

        if (isset($validated['child_rules'])) {
            $breRule->syncChildRules($validated['child_rules']);
        }

        if (isset($validated['icmcdw_fields'])) {
            $breRule->syncIcmCDWFields($validated['icmcdw_fields']);
        }

        return new BRERuleResource($breRule);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'internal_description' => 'nullable|string|max:1000',
            'rule_inputs' => 'array',
            'rule_inputs.*.id' => 'integer|exists:bre_fields,id',
            'rule_outputs' => 'array',
            'rule_outputs.*.id' => 'integer|exists:bre_fields,id',
            'parent_rules' => 'array',
            'parent_rules.*.id' => 'integer|exists:bre_rules,id',
            'child_rules' => 'array',
            'child_rules.*.id' => 'integer|exists:bre_rules,id',
            'icmcdw_fields' => 'array',
            'icmcdw_fields.*.id' => 'integer|exists:icm_cdw_fields,id',
        ]);

        $breRule = BRERule::findOrFail($id);

        $breRule->update($validated);

        if (isset($validated['rule_inputs'])) {
            $breRule->syncRuleInputs($validated['rule_inputs']);
        }

        if (isset($validated['rule_outputs'])) {
            $breRule->syncRuleOutputs($validated['rule_outputs']);
        }

        if (isset($validated['parent_rules'])) {
            $breRule->syncParentRules($validated['parent_rules']);
        }

        if (isset($validated['child_rules'])) {
            $breRule->syncChildRules($validated['child_rules']);
        }

        if (isset($validated['icmcdw_fields'])) {
            $breRule->syncIcmCDWFields($validated['icmcdw_fields']);
        }

        return new BRERuleResource($breRule);
    }

    public function destroy($id)
    {
        $breRule = BRERule::findOrFail($id);
        $breRule->delete();

        return response()->json(null, 204);
    }
}
