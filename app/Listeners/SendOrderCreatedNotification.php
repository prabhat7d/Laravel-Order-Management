<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use Illuminate\Support\Facades\Log;

class SendOrderCreatedNotification
{
    public function handle(OrderCreated $event): void
    {
        Log::info('Order created notification', [
            'order_id' => $event->order->id,
            'order_number' => $event->order->order_number,
        ]);
    }
}
