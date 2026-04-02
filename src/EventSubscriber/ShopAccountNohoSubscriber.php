<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Removes address book and order history from the shop account sidebar menu;
 * redirects direct access to the address book to the dashboard.
 */
final class ShopAccountNohoSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run after the firewall so only authenticated hits are redirected away from address-book.
            KernelEvents::REQUEST => ['onKernelRequest', 0],
            'sylius.menu.shop.account' => ['onShopAccountMenu', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($event->hasResponse()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_contains($request->getPathInfo(), '/account/address-book')) {
            return;
        }

        $event->setResponse(
            new RedirectResponse(
                $this->urlGenerator->generate(
                    'sylius_shop_account_dashboard',
                    ['_locale' => $request->getLocale()],
                ),
            ),
        );
    }

    public function onShopAccountMenu(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $menu->removeChild('address_book');
    }
}
