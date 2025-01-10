<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemMessageSummaryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'data_group' => $this->errorDataGroup->name ?? null,
        ];
    }
}
