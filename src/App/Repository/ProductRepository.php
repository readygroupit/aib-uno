<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Product;

final class ProductRepository extends AbstractRepository
{
    protected string $table = 'products';
    protected string $modelClass = Product::class;
}
