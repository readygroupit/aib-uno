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
    public ?string $relationship = null;
    public ?string $importance = null;
    public ?string $sector = null;
    public ?string $customerSince = null;
    public ?int $accountUserId = null;
    public ?int $referrerCustomerId = null;
    public ?string $referralPercent = null;
    public ?string $referralTerms = null;
    public ?string $preferredPaymentMethod = null;
    public ?int $autoPay = null;
    public ?string $portalToken = null;
    public ?string $stripeCustomerId = null;
    public ?string $paymentMandate = null;

    /** Ragione sociale se c'e', altrimenti nome e cognome: come il cliente va chiamato in ogni schermata. */
    public function displayName(): string
    {
        $person = trim("{$this->firstName} {$this->lastName}");

        return $this->companyName ?: ($person !== '' ? $person : '(senza nome)');
    }
}
