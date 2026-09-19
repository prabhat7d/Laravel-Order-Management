<?php

namespace App\Domain\Payments\Strategies;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Payment;
use RuntimeException;
use App\Domain\Payments\PaymentStatus;

class FailingPaymentGateway implements PaymentGateway
{
    public function charge(Order $order): Payment
    {
        $payment = $order->payments()->create([
            'provider' => 'failing',
            'transaction_id' => null,
            'amount' => $order->total,
            'status' => PaymentStatus::FAILED,
            'paid_at' => null,
        ]);

        throw new RuntimeException('Payment failed.');
    }
}
