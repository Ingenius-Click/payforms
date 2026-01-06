<?php

namespace Ingenius\Payforms\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class PayFormDataResource extends JsonResource {

    public function toArray(\Illuminate\Http\Request $request): array {
        $array = $this->resource->toArray();
        return [
            ...$array
        ];
    }

}