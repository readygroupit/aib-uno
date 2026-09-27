<?php

declare(strict_types=1);

namespace App\Model;

final class Product extends AbstractModel
{
    public ?string $name = null;
    public ?string $sku = null;
    public ?string $category = null;
    public ?string $description = null;
    public ?string $stage = null;
    public ?string $costPrice = null;
    public ?string $sellingPrice = null;
    public ?string $taxRatePercent = null;
    public ?int $stockQuantity = null;
    public ?int $lowStockThreshold = null;
    public ?string $weightKg = null;
    public ?string $imageUrl = null;
}
