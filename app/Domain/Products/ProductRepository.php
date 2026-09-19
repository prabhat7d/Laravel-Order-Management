<?php

namespace App\Domain\Products;

use App\Models\Product;

interface ProductRepository
{
    public function findForUpdate(int $id): ?Product;
}
