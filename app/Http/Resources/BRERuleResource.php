<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BRERuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'description' => $this->description,
            'internal_description' => $this->internal_description,
            'rule_inputs' => $this->breInputs,
            'rule_outputs' => $this->breOutputs,
            'parent_rules' => $this->parentRules,
            'child_rules' => $this->childRules,
            'icmcdw_fields' => $this->icmcdwFields,
            'siebel_business_object_inputs' => $this->getSiebelBusinessObjects('inputs'),
            'siebel_business_object_outputs' => $this->getSiebelBusinessObjects('outputs'),
            'siebel_business_component_inputs' => $this->getSiebelBusinessComponents('inputs'),
            'siebel_business_component_outputs' => $this->getSiebelBusinessComponents('outputs'),
        ];
    }
}
