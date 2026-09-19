<?php

namespace App\Infrastructure\Orders;

use App\Domain\Orders\OrderRepository;
use App\Models\Order;
use App\Models\OrderLog;

class EloquentOrderRepository implements OrderRepository
{
    public function findById(int $id): ?Order
    {
        return Order::find($id);
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return Order::where('order_number', $orderNumber)->first();
    }

    public function getByUserId(int $userId, int $perPage = 15)
    {
        return Order::query()
            ->where('user_id', $userId)
            ->with(['items', 'payments'])
            ->latest()
            ->paginate($perPage);
    }

    public function save(Order $order): Order
    {
        $order->save();

        return $order;
    }

    public function getLogs(int $orderId)
    {
        return OrderLog::query()
            ->where('order_id', $orderId)
            ->latest()
            ->get();
    }

    public function findByIdWithLogs(int $id): ?Order
    {
        return Order::query()
            ->with('logs')
            ->find($id);
    }
}
