<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BREFieldResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'help_text' => $this->help_text,
            'data_type_id' => $this->data_type_id,
            'description' => $this->description,
            'input_output_type' => $this->getInputOutputType(),
            'rule_inputs' => $this->breInputs,
            'rule_outputs' => $this->breOutputs,
            'field_group_names' => $this->fieldGroupNames,
            'field_groups' => $this->breFieldGroups,
            'icmcdw_fields' => $this->icmcdwFields,
        ];
    }
}