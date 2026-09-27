<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Appointment;

final class AppointmentRepository extends AbstractRepository
{
    protected string $table = 'appointments';
    protected string $modelClass = Appointment::class;
}
