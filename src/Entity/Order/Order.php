<?php

declare(strict_types=1);

namespace App\Entity\Order;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Order as BaseOrder;
use Sylius\MolliePlugin\Entity\AbandonedEmailOrderTrait;
use Sylius\MolliePlugin\Entity\MolliePaymentIdOrderTrait;
use Sylius\MolliePlugin\Entity\OrderInterface;
use Sylius\MolliePlugin\Entity\QRCodeOrderTrait;
use Sylius\MolliePlugin\Entity\RecurringOrderTrait;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order')]
class Order extends BaseOrder implements OrderInterface
{
    use MolliePaymentIdOrderTrait;
    use QRCodeOrderTrait;
    use RecurringOrderTrait;
    use AbandonedEmailOrderTrait;

    #[ORM\Column(name: 'reservation_start_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reservationStartAt = null;

    #[ORM\Column(name: 'reservation_end_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reservationEndAt = null;

    public function getReservationStartAt(): ?\DateTimeImmutable
    {
        return $this->reservationStartAt;
    }

    public function setReservationStartAt(?\DateTimeImmutable $reservationStartAt): void
    {
        $this->reservationStartAt = $reservationStartAt;
    }

    public function getReservationEndAt(): ?\DateTimeImmutable
    {
        return $this->reservationEndAt;
    }

    public function setReservationEndAt(?\DateTimeImmutable $reservationEndAt): void
    {
        $this->reservationEndAt = $reservationEndAt;
    }
}
