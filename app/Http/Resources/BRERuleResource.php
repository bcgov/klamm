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
        ];
    }
}
