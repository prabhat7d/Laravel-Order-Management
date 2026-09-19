<?php

namespace App\Domain\Orders;

use App\Models\Order;

interface OrderRepository
{
    public function findById(int $id): ?Order;

    public function findByOrderNumber(string $orderNumber): ?Order;

    public function getByUserId(int $userId, int $perPage = 15);

    public function save(Order $order): Order;

    public function getLogs(int $orderId);

    public function findByIdWithLogs(int $id): ?Order;
}
