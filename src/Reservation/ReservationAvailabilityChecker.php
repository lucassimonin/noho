<?php

declare(strict_types=1);

namespace App\Reservation;

use App\Entity\Order\Order as AppOrder;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;

final class ReservationAvailabilityChecker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Half-open interval [start, end): conflicts with another confirmed booking for the same variant.
     */
    public function hasConflict(
        ProductVariantInterface $variant,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        OrderInterface $currentOrder,
    ): bool {
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('COUNT(o.id)')
            ->from(AppOrder::class, 'o')
            ->innerJoin('o.items', 'oi')
            ->where('oi.variant = :variant')
            ->andWhere('o.checkoutState = :completed')
            ->andWhere('o.state != :cancelled')
            ->andWhere('o.reservationStartAt IS NOT NULL')
            ->andWhere('o.reservationEndAt IS NOT NULL')
            ->andWhere('o.reservationStartAt < :end')
            ->andWhere('o.reservationEndAt > :start')
            ->setParameter('variant', $variant)
            ->setParameter('completed', OrderCheckoutStates::STATE_COMPLETED)
            ->setParameter('cancelled', BaseOrderInterface::STATE_CANCELLED)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
        ;

        $id = $currentOrder->getId();
        if (null !== $id) {
            $qb->andWhere('o.id != :oid')->setParameter('oid', $id);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
