<?php

namespace Ingenius\Payforms\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayFormDataShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payform_id' => $this->payform_id,
            'icon' => asset($this->icon),
            'name' => $this->name,
            'description' => $this->description,
            'active' => $this->active,
            'currencies' => $this->currencies,
            'args' => $this->args,
            'rules' => $this->getRulesAttribute()
        ];
    }
}