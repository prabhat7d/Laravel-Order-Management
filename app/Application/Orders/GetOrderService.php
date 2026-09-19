<?php

namespace App\Application\Orders;

use App\Domain\Orders\OrderRepository;
use App\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class GetOrderService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
    ) {}

    public function execute(int $orderId): Order
    {
        $order = $this->orderRepository->findById($orderId);

        if (!$order) {
            abort(404);
        }

        return $order->load(['items', 'payments']);
    }

    public function getLogs(int $orderId)
    {
        $order = $this->orderRepository->findByIdWithLogs($orderId);

        if (!$order) {
            abort(404);
        }

        return $order->logs;
    }
}
