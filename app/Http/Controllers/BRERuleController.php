<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BRERule;
use App\Http\Resources\BRERuleResource;
use Illuminate\Support\Facades\Validator;

class BRERuleController extends Controller
{
    public function index(Request $request)
    {
        $query = BRERule::query();
        $query->with([
            'breInputs.breDataType.breValueType',
            'breInputs.breDataValidation.breValidationType',
            'breInputs.breFieldGroups',
            'breInputs.childFields',
            'breInputs.icmcdwFields',
            'breInputs.siebelBusinessObjects',
            'breInputs.siebelBusinessComponents',
            'breInputs.siebelApplets',
            'breInputs.siebelTables',
            'breInputs.siebelFields',

            'breOutputs.breDataType.breValueType',
            'breOutputs.breDataValidation.breValidationType',
            'breOutputs.breFieldGroups',
            'breOutputs.childFields',
            'breOutputs.icmcdwFields',
            'breOutputs.siebelBusinessObjects',
            'breOutputs.siebelBusinessComponents',
            'breOutputs.siebelApplets',
            'breOutputs.siebelTables',
            'breOutputs.siebelFields',

            'parentRules.breInputs.breDataType.breValueType',
            'parentRules.breInputs.breDataValidation.breValidationType',
            'parentRules.breOutputs.breDataType.breValueType',
            'parentRules.breOutputs.breDataValidation.breValidationType',

            'childRules.breInputs.breDataType.breValueType',
            'childRules.breInputs.breDataValidation.breValidationType',
            'childRules.breOutputs.breDataType.breValueType',
            'childRules.breOutputs.breDataValidation.breValidationType',

            'icmcdwFields',
            'siebelBusinessObjects',
            'siebelBusinessComponents',
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

        if ($request->has('field_group')) {
            $query->whereHas('breFieldGroups', function ($query) use ($request) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->field_group) . '%']);
            });
        }

        if ($request->has('rule_inputs')) {
            $query->whereHas('breInputs', function ($query) use ($request) {
                if ($request->filled('rule_inputs')) {
                    $input = $request->rule_inputs;
                    if (is_numeric($input)) {
                        $query->where('bre_fields.id', $input);
                    } else {
                        $query->whereRaw('LOWER(bre_fields.name) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(bre_fields.label) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(bre_fields.description) LIKE ?', ['%' . strtolower($input) . '%']);
                    }
                }
            });
        }

        if ($request->has('rule_outputs')) {
            $query->whereHas('breOutputs', function ($query) use ($request) {
                if ($request->filled('rule_outputs')) {
                    $input = $request->rule_outputs;
                    if (is_numeric($input)) {
                        $query->where('bre_fields.id', $input);
                    } else {
                        $query->whereRaw('LOWER(bre_fields.name) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(bre_fields.label) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(bre_fields.description) LIKE ?', ['%' . strtolower($input) . '%']);
                    }
                }
            });
        }

        if ($request->has('icmcdw_fields')) {
            $query->whereHas('icmcdwFields', function ($query) use ($request) {
                if ($request->filled('icmcdw_fields')) {
                    $input = $request->icmcdw_fields;
                    if (is_numeric($input)) {
                        $query->where('icm_cdw_fields.id', $input);
                    } else {
                        $query->whereRaw('LOWER(icm_cdw_fields.name) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(icm_cdw_fields.field) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(icm_cdw_fields.panel_type) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(icm_cdw_fields.entity) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(icm_cdw_fields.path) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(icm_cdw_fields.subject_area) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(icm_cdw_fields.applet) LIKE ?', ['%' . strtolower($input) . '%']);
                    }
                }
            });
        }

        if ($request->has('siebel_business_objects')) {
            $query->whereHas('siebelBusinessObjects', function ($query) use ($request) {
                if ($request->filled('siebel_business_objects')) {
                    $input = $request->siebel_business_objects;
                    if (is_numeric($input)) {
                        $query->where('siebel_business_objects.id', $input);
                    } else {
                        $query->whereRaw('LOWER(siebel_business_objects.name) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(siebel_business_objects.description) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(siebel_business_objects.comments) LIKE ?', ['%' . strtolower($input) . '%']);
                    }
                }
            });
        }

        if ($request->has('siebel_business_components')) {
            $query->whereHas('siebelBusinessComponents', function ($query) use ($request) {
                if ($request->filled('siebel_business_components')) {
                    $input = $request->siebel_business_components;
                    if (is_numeric($input)) {
                        $query->where('siebel_business_components.id', $input);
                    } else {
                        $query->whereRaw('LOWER(siebel_business_components.name) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(siebel_business_components.description) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(siebel_business_components.comments) LIKE ?', ['%' . strtolower($input) . '%']);
                    }
                }
            });
        }

        if ($request->has('parent_rules')) {
            $query->whereHas('parentRules', function ($query) use ($request) {
                if ($request->filled('parent_rules')) {
                    $input = $request->parent_rules;
                    if (is_numeric($input)) {
                        $query->where('child_rule_id', $input);
                    } else {
                        $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($input) . '%']);
                    }
                }
            });
        }

        if ($request->has('child_rules')) {
            $query->whereHas('childRules', function ($query) use ($request) {
                if ($request->filled('child_rules')) {
                    $input = $request->child_rules;
                    if (is_numeric($input)) {
                        $query->where('parent_rule_id', $input);
                    } else {
                        $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($input) . '%']);
                    }
                }
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
                    ->orWhereRaw('LOWER(description) LIKE ?', ['%' . $searchTerm . '%']);
            });
        }

        $breRules = $query->get();
        return BRERuleResource::collection($breRules);
    }

    public function show($id)
    {
        $query = BRERule::with([
            'breInputs.breDataType.breValueType',
            'breInputs.breDataValidation.breValidationType',
            'breInputs.breFieldGroups',
            'breInputs.childFields',
            'breInputs.icmcdwFields',
            'breInputs.siebelBusinessObjects',
            'breInputs.siebelBusinessComponents',
            'breInputs.siebelApplets',
            'breInputs.siebelTables',
            'breInputs.siebelFields',

            'breOutputs.breDataType.breValueType',
            'breOutputs.breDataValidation.breValidationType',
            'breOutputs.breFieldGroups',
            'breOutputs.childFields',
            'breOutputs.icmcdwFields',
            'breOutputs.siebelBusinessObjects',
            'breOutputs.siebelBusinessComponents',
            'breOutputs.siebelApplets',
            'breOutputs.siebelTables',
            'breOutputs.siebelFields',

            'parentRules.breInputs.breDataType.breValueType',
            'parentRules.breInputs.breDataValidation.breValidationType',
            'parentRules.breOutputs.breDataType.breValueType',
            'parentRules.breOutputs.breDataValidation.breValidationType',

            'childRules.breInputs.breDataType.breValueType',
            'childRules.breInputs.breDataValidation.breValidationType',
            'childRules.breOutputs.breDataType.breValueType',
            'childRules.breOutputs.breDataValidation.breValidationType',

            'icmcdwFields',
            'siebelBusinessObjects',
            'siebelBusinessComponents',
        ]);

        if (is_numeric($id)) {
            $breRule = $query->findOrFail($id);
        } else {
            $breRule = $query->where('name', $id)->firstOrFail();
        }

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
            'siebel_business_object_inputs' => 'array',
            'siebel_business_object_inputs.*.id' => 'integer|exists:siebel_business_objects,id',
            'siebel_business_object_outputs' => 'array',
            'siebel_business_object_outputs.*.id' => 'integer|exists:siebel_business_objects,id',
            'siebel_business_component_inputs' => 'array',
            'siebel_business_component_inputs.*.id' => 'integer|exists:siebel_business_components,id',
            'siebel_business_component_outputs' => 'array',
            'siebel_business_component_outputs.*.id' => 'integer|exists:siebel_business_components,id',
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

        if (isset($validated['siebel_business_object_inputs'])) {
            $breRule->syncSiebelBusinessObjects($validated['siebel_business_object_inputs'], 'inputs');
        }

        if (isset($validated['siebel_business_object_outputs'])) {
            $breRule->syncSiebelBusinessObjects($validated['siebel_business_object_outputs'], 'outputs');
        }

        if (isset($validated['siebel_business_component_inputs'])) {
            $breRule->syncSiebelBusinessComponents($validated['siebel_business_component_inputs'], 'inputs');
        }

        if (isset($validated['siebel_business_component_outputs'])) {
            $breRule->syncSiebelBusinessComponents($validated['siebel_business_component_outputs'], 'outputs');
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
            'siebel_business_object_inputs' => 'array',
            'siebel_business_object_inputs.*.id' => 'integer|exists:siebel_business_objects,id',
            'siebel_business_object_outputs' => 'array',
            'siebel_business_object_outputs.*.id' => 'integer|exists:siebel_business_objects,id',
            'siebel_business_component_inputs' => 'array',
            'siebel_business_component_inputs.*.id' => 'integer|exists:siebel_business_components,id',
            'siebel_business_component_outputs' => 'array',
            'siebel_business_component_outputs.*.id' => 'integer|exists:siebel_business_components,id',
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

        if (isset($validated['siebel_business_object_inputs'])) {
            $breRule->syncSiebelBusinessObjects($validated['siebel_business_object_inputs'], 'inputs');
        }

        if (isset($validated['siebel_business_object_outputs'])) {
            $breRule->syncSiebelBusinessObjects($validated['siebel_business_object_outputs'], 'outputs');
        }

        if (isset($validated['siebel_business_component_inputs'])) {
            $breRule->syncSiebelBusinessComponents($validated['siebel_business_component_inputs'], 'inputs');
        }

        if (isset($validated['siebel_business_component_outputs'])) {
            $breRule->syncSiebelBusinessComponents($validated['siebel_business_component_outputs'], 'outputs');
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
