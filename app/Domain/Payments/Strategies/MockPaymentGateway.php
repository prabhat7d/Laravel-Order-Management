<?php

namespace App\Domain\Payments\Strategies;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Payment;
use RuntimeException;
use App\Domain\Payments\PaymentStatus;

class MockPaymentGateway implements PaymentGateway
{
    public function charge(Order $order): Payment
    {
        if ($order->total <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        return $order->payments()->create([
            'provider' => 'mock',
            'transaction_id' => 'TXN-' . strtoupper(str()->random(12)),
            'amount' => $order->total,
            'status' => PaymentStatus::PAID,
            'paid_at' => now(),
        ]);
    }
}
