<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'provider' => $this->provider,
            'status' => $this->status,
            'message' => $this->message,
            'context' => $this->context,
            'created_at' => $this->created_at,
        ];
    }
}
