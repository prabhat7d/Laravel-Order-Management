<?php

namespace App\Domain\Orders\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(string $productName)
    {
        parent::__construct(
            "Insufficient stock for {$productName}."
        );
    }
}
