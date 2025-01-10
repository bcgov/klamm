<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemMessageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'icm_error_solution' => $this->icm_error_solution,
            'explanation' => $this->explanation,
            'fix' => $this->fix,
            'service_desk' => $this->service_desk,
            'limited_data' => $this->limited_data,
            'last_updated' => $this->updated_at,
            'error_entity' => $this->errorEntity->name ?? null,
            'error_data_group' => $this->errorDataGroup->name ?? null,
            'error_integration_state' => $this->errorIntegrationState->name ?? null,
            'error_actor' => $this->errorActor->name ?? null,
            'error_source' => $this->errorSource->name ?? null,
        ];
    }
}
