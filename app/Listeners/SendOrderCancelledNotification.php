<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Application\Orders\OrderLogService;

class SendOrderCancelledNotification
{
    public function __construct(
        private readonly OrderLogService $orderLogService,
    ) {}

    public function handle(OrderCancelled $event): void
    {
        $this->orderLogService->record(
            $event->order,
            'order.cancelled',
            'Order cancelled successfully.',
            null,
            $event->order->status->value,
        );
    }
}
