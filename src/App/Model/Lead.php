<?php

declare(strict_types=1);

namespace App\Model;

final class Lead extends AbstractModel
{
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $companyName = null;
    public ?string $jobTitle = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $mobilePhone = null;
    public ?string $website = null;
    public ?string $address = null;
    public ?string $city = null;
    public ?string $postalCode = null;
    public ?string $stage = null;
    public ?int $priority = null;
    public ?string $estimatedValue = null;
    public ?int $assignedUserId = null;
    public ?string $nextFollowUpAt = null;
    public ?string $lastContactedAt = null;
    public ?string $lostReason = null;
    public ?int $convertedCustomerId = null;
    public ?string $convertedAt = null;
    public ?string $notes = null;
    public ?string $sourceChannel = null;
    public ?string $campaign = null;
    public ?string $keyword = null;
    public ?string $landingPage = null;
}
