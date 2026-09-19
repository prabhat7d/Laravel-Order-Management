<?php

namespace App\Application\Orders;

use App\Models\Order;
use App\Models\OrderLog;

class OrderLogService
{
    public function record(
        Order $order,
        string $event,
        ?string $message = null,
        ?string $provider = null,
        ?string $status = null,
        ?array $context = null,
    ): OrderLog {
        return $order->logs()->create([
            'event' => $event,
            'provider' => $provider,
            'status' => $status,
            'message' => $message,
            'context' => $context,
        ]);
    }
}
