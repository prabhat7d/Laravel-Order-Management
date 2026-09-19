<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'currency' => $this->currency,

            'items' => OrderItemResource::collection(
                $this->whenLoaded('items')
            ),

            'payments' => PaymentResource::collection(
                $this->whenLoaded('payments')
            ),

            'created_at' => $this->created_at,
        ];
    }
}
