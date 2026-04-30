<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Order\OrderItem;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ReservationPeriodConstraintValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ReservationPeriodConstraint) {
            throw new UnexpectedTypeException($constraint, ReservationPeriodConstraint::class);
        }

        if (!$value instanceof OrderItem) {
            return;
        }

        $start = $value->getReservationStartAt();
        $end = $value->getReservationEndAt();

        if (null === $start || null === $end) {
            return;
        }

        if ($end <= $start) {
            $this->context->buildViolation($constraint->message)
                ->setTranslationDomain('messages')
                ->atPath('reservationEndAt')
                ->addViolation();

            return;
        }

        $minSeconds = $constraint->minNights * 86400;
        if (($end->getTimestamp() - $start->getTimestamp()) < $minSeconds) {
            $this->context->buildViolation($constraint->minNightsMessage)
                ->setTranslationDomain('messages')
                ->atPath('reservationEndAt')
                ->setParameter('%min%', (string) $constraint->minNights)
                ->addViolation();
        }
    }
}
