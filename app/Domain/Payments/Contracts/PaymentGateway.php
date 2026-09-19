<?php

namespace App\Domain\Payments\Contracts;

use App\Models\Order;
use App\Models\Payment;

interface PaymentGateway
{
    public function charge(Order $order): Payment;
}
