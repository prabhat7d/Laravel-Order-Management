<?php

namespace App\Infrastructure\Products;

use App\Domain\Products\ProductRepository;
use App\Models\Product;

class EloquentProductRepository implements ProductRepository
{
    public function findForUpdate(int $id): ?Product
    {
        return Product::query()
            ->lockForUpdate()
            ->find($id);
    }
}
