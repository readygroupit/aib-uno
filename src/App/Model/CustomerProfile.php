<?php

declare(strict_types=1);

namespace App\Model;

final class CustomerProfile extends AbstractModel
{
    public ?int $customerId = null;
    public ?string $history = null;
    public ?string $products = null;
    public ?string $mission = null;
    public ?string $audience = null;
    public ?string $competitors = null;
    public ?string $styleLikes = null;
    public ?string $styleDislikes = null;
    public ?string $toneOfVoice = null;
    public ?string $brandUsage = null;
    public ?string $seasonality = null;
    public ?string $notes = null;
    public ?string $lastSource = null;
}
