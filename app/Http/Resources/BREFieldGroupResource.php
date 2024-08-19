<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BREFieldGroupResource extends JsonResource
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
            'bre_fields' => $this->breFields,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
