<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Order\OrderItem;
use App\Reservation\ReservationAvailabilityChecker;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ReservationAvailabilityConstraintValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ReservationAvailabilityChecker $availabilityChecker,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ReservationAvailabilityConstraint) {
            throw new UnexpectedTypeException($constraint, ReservationAvailabilityConstraint::class);
        }

        if (!$value instanceof OrderItem) {
            return;
        }

        $variant = $value->getVariant();
        $start = $value->getReservationStartAt();
        $end = $value->getReservationEndAt();

        if (null === $variant || null === $start || null === $end) {
            return;
        }

        $order = $value->getOrder();
        if (null === $order) {
            return;
        }

        if ($this->availabilityChecker->hasConflict($variant, $start, $end, $order, $value)) {
            $this->context->buildViolation($constraint->message)
                ->setTranslationDomain('messages')
                ->addViolation();
        }
    }
}
