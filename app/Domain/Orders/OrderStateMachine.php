<?php

namespace App\Domain\Orders;

use DomainException;

class OrderStateMachine
{
    private const TRANSITIONS = [
        OrderStatus::PENDING->value => [
            OrderStatus::CONFIRMED,
            OrderStatus::FAILED,
            OrderStatus::CANCELLED,
        ],

        OrderStatus::CONFIRMED->value => [
            OrderStatus::PROCESSING,
            OrderStatus::CANCELLED,
        ],

        OrderStatus::PROCESSING->value => [
            OrderStatus::COMPLETED,
            OrderStatus::CANCELLED,
        ],

        OrderStatus::COMPLETED->value => [],

        OrderStatus::CANCELLED->value => [],

        OrderStatus::FAILED->value => [],
    ];

    public function canTransition(
        OrderStatus $from,
        OrderStatus $to,
    ): bool {
        return in_array(
            $to,
            self::TRANSITIONS[$from->value] ?? [],
            true
        );
    }

    public function transition(
        OrderStatus $from,
        OrderStatus $to,
    ): OrderStatus {
        if (!$this->canTransition($from, $to)) {
            throw new DomainException(
                "Invalid order status transition from {$from->value} to {$to->value}."
            );
        }

        return $to;
    }
}
