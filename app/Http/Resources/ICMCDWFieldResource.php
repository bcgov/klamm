<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ICMCDWFieldResource extends JsonResource
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
            'field' => $this->field,
            'panel_type' => $this->panel_type,
            'entity' => $this->entity,
            'path' => $this->path,
            'subject_area' => $this->subject_area,
            'applet' => $this->applet,
            'datatype' => $this->datatype,
            'field_input_max_length' => $this->field_input_max_length,
            'ministry' => $this->ministry,
            'cdw_ui_caption' => $this->cdw_ui_caption,
            'cdw_table_name' => $this->cdw_table_name,
            'cdw_column_name' => $this->cdw_column_name,
            'bre_fields' => $this->breFields,
        ];
    }
}
