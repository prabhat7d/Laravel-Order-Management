<?php

namespace App\Domain\Orders\Exceptions;

use RuntimeException;

class ProductUnavailableException extends RuntimeException
{
    public function __construct(int $productId)
    {
        parent::__construct(
            "Product {$productId} is unavailable."
        );
    }
}
