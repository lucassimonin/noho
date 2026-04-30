<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Order\Order as AppOrder;
use App\Reservation\ReservationAvailabilityChecker;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

final class ReservationCheckoutGuardListener
{
    public function __construct(
        private readonly ReservationAvailabilityChecker $availabilityChecker,
    ) {
    }

    #[AsEventListener(event: 'workflow.sylius_order_checkout.guard.complete')]
    public function onCheckoutComplete(GuardEvent $event): void
    {
        $order = $event->getSubject();
        if (!$order instanceof OrderInterface || !$order instanceof AppOrder) {
            return;
        }

        if ($order->getItems()->count() !== 1) {
            $event->addTransitionBlocker(
                new TransitionBlocker(
                    'A booking order must contain exactly one property line.',
                    'RESERVATION_SINGLE_LINE',
                ),
            );

            return;
        }

        $item = $order->getItems()->first();
        $variant = $item->getVariant();
        $start = $order->getReservationStartAt();
        $end = $order->getReservationEndAt();

        if (null === $variant || null === $start || null === $end) {
            $event->addTransitionBlocker(
                new TransitionBlocker('Reservation dates are missing.', 'RESERVATION_DATES'),
            );

            return;
        }

        if ($this->availabilityChecker->hasConflict($variant, $start, $end, $order)) {
            $event->addTransitionBlocker(
                new TransitionBlocker(
                    'These dates are no longer available for this property.',
                    'RESERVATION_CONFLICT',
                ),
            );
        }
    }
}
