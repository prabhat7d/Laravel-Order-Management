<?php

namespace App\Application\Orders;

use App\Domain\Orders\OrderRepository;
use App\Domain\Orders\OrderStateMachine;
use App\Domain\Orders\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Events\OrderCancelled;

class CancelOrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function execute(int $orderId): Order
    {
        return DB::transaction(function () use ($orderId) {
            $order = $this->orderRepository->findById($orderId);

            if (!$order) {
                abort(404);
            }

            $order = Order::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($order->id);

            $newStatus = $this->stateMachine->transition(
                $order->status,
                OrderStatus::CANCELLED,
            );

            foreach ($order->items as $item) {
                Product::query()
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->increment('stock_quantity', $item->quantity);
            }

            $order->update([
                'status' => $newStatus,
            ]);

            OrderCancelled::dispatch($order);

            return $order->fresh(['items', 'payments']);
        });
    }
}
