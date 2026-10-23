<?php

declare(strict_types=1);

namespace App\Model;

final class CompanyProfile extends AbstractModel
{
    public ?string $companyName = null;
    public ?string $vatNumber = null;
    public ?string $taxCode = null;
    public ?string $taxRegime = null;
    public ?string $address = null;
    public ?string $postalCode = null;
    public ?string $city = null;
    public ?string $province = null;
    public ?string $country = null;
    public ?string $email = null;
    public ?string $pec = null;
    public ?string $phone = null;
    public ?string $iban = null;
    public ?string $defaultTaxRate = null;
}
