<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ReservationPeriodConstraint extends Constraint
{
    public string $message = 'noho.reservation.period_invalid';

    public string $minNightsMessage = 'noho.reservation.min_nights';

    public int $minNights = 3;

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
