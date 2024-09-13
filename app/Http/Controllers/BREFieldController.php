<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BREField;
use App\Http\Resources\BREFieldResource;
use Illuminate\Support\Facades\Validator;

class BREFieldController extends Controller
{
    public function index(Request $request)
    {
        $query = BREField::query();

        if ($request->has('name')) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($request->name) . '%']);
        }
        if ($request->has('data_type_id')) {
            $query->where('data_type_id', $request->data_type_id);
        }

        if ($request->has('label')) {
            $query->whereRaw('LOWER(label) LIKE ?', ['%' . strtolower($request->label) . '%']);
        }

        if ($request->has('description')) {
            $query->whereRaw('LOWER(description) LIKE ?', ['%' . strtolower($request->description) . '%']);
        }

        if ($request->has('help_text')) {
            $query->whereRaw('LOWER(help_text) LIKE ?', ['%' . strtolower($request->help_text) . '%']);
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
                        $query->where('bre_rules.id', $input);
                    } else {
                        $query->whereRaw('LOWER(bre_rules.name) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(bre_rules.label) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(bre_rules.description) LIKE ?', ['%' . strtolower($input) . '%']);
                    }
                }
            });
        }

        if ($request->has('rule_outputs')) {
            $query->whereHas('breOutputs');
        }

        if ($request->has('rule_outputs')) {
            $query->whereHas('breOutputs', function ($query) use ($request) {
                if ($request->filled('rule_outputs')) {
                    $input = $request->rule_outputs;
                    if (is_numeric($input)) {
                        $query->where('bre_rules.id', $input);
                    } else {
                        $query->whereRaw('LOWER(bre_rules.name) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(bre_rules.label) LIKE ?', ['%' . strtolower($input) . '%'])
                            ->orWhereRaw('LOWER(bre_rules.description) LIKE ?', ['%' . strtolower($input) . '%']);
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



        if ($request->has('input_output_type')) {
            $inputOutputType = strtolower($request->input_output_type);

            $query->where(function ($query) use ($inputOutputType) {
                if ($inputOutputType === 'input') {
                    $query->whereHas('breInputs');
                } elseif ($inputOutputType === 'output') {
                    $query->whereHas('breOutputs');
                } else {
                    $query->whereHas('breInputs')
                        ->orWhereHas('breOutputs');
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

        $breFields = $query->get();
        return BREFieldResource::collection($breFields);
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
            'child_fields' => 'nullable|array',
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

        if (isset($validated['child_fields'])) {
            $breField->syncChildFields($validated['child_fields']);
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
            'child_fields' => 'nullable|array',
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

        if (isset($validated['child_fields'])) {
            $breField->syncChildFields($validated['child_fields']);
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
