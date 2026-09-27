<?php

declare(strict_types=1);

namespace App\Model;

final class Customer extends AbstractModel
{
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $companyName = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $mobilePhone = null;
    public ?string $website = null;
    public ?string $address = null;
    public ?int $municipalityId = null;
    public ?string $postalCode = null;
    public ?string $vatNumber = null;
    public ?string $sdiCode = null;
    public ?string $pec = null;
    public ?string $iban = null;
    public ?int $paymentTermsDays = null;
    public ?string $notes = null;
}
