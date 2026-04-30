<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ReservationAvailabilityConstraint extends Constraint
{
    public string $message = 'noho.reservation.unavailable';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
