<?php

declare(strict_types=1);

namespace App\EventListener;

use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Component\Order\SyliusCartEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;
use Webmozart\Assert\Assert;

/**
 * One stay per order: keep a single order line (last added product wins).
 */
final class SingleReservationCartListener
{
    #[AsEventListener(event: SyliusCartEvents::CART_ITEM_ADD, priority: 10)]
    public function clearExistingLinesBeforeAdd(GenericEvent $event): void
    {
        $command = $event->getSubject();
        Assert::isInstanceOf($command, AddToCartCommandInterface::class);

        $cart = $command->getCart();
        foreach ($cart->getItems()->toArray() as $line) {
            $cart->removeItem($line);
        }
    }
}
