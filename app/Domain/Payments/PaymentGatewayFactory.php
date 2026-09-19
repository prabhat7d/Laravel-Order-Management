<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\Strategies\MockPaymentGateway;
use InvalidArgumentException;
use App\Domain\Payments\Strategies\FailingPaymentGateway;

class PaymentGatewayFactory
{
    public function make(string $provider): PaymentGateway
    {
        return match ($provider) {
            'mock' => app(MockPaymentGateway::class),
            'failing' => app(FailingPaymentGateway::class),

            default => throw new InvalidArgumentException(
                "Unsupported payment provider: {$provider}"
            ),
        };
    }
}
