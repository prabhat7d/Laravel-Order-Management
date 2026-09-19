<?php

namespace App\Application\Orders;

use App\Domain\Orders\OrderRepository;
use App\Domain\Orders\OrderStateMachine;
use App\Domain\Orders\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class UpdateOrderStatusService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly OrderStateMachine $stateMachine,
        private readonly OrderLogService $orderLogService,
    ) {}

    public function execute(
        int $orderId,
        OrderStatus $newStatus,
    ): Order {
        return DB::transaction(function () use ($orderId, $newStatus) {
            $order = $order = Order::query()
                ->lockForUpdate()
                ->find($orderId);

            if (!$order) {
                throw new ModelNotFoundException();
            }

            $oldStatus = $order->status;

            $order->update([
                'status' => $this->stateMachine->transition(
                    $oldStatus,
                    $newStatus,
                ),
            ]);

            $this->orderLogService->record(
                $order,
                'order.status_changed',
                "Order status changed from {$oldStatus->value} to {$newStatus->value}.",
                null,
                $newStatus->value,
                [
                    'from' => $oldStatus->value,
                    'to' => $newStatus->value,
                ],
            );

            return $order->fresh(['items', 'payments']);
        });
    }
}
