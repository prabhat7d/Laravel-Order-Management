<?php

namespace App\Application\Orders;

use App\Domain\Orders\OrderRepository;

class GetOrdersService
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
    ) {}

    public function execute(int $userId, int $perPage = 15)
    {
        return $this->orderRepository->getByUserId(
            $userId,
            $perPage
        );
    }
}
