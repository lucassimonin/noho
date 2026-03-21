<?php

declare(strict_types=1);

namespace App\Twig\Components\Shop;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('noho_catalog_filters')]
final class CatalogFiltersComponent
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[LiveProp]
    public string $slug = '';

    #[LiveProp]
    public string $locale = '';

    #[LiveProp(writable: true)]
    public string $voyageurs = '';

    #[LiveProp(writable: true)]
    public string $chambres = '';

    #[LiveProp(writable: true)]
    public string $dateStart = '';

    #[LiveProp(writable: true)]
    public string $dateEnd = '';

    /** Synced from request on first render; price changes use client-side URL + noho:catalog:update (same as sort). */
    #[LiveProp]
    public string $priceBucket = '';

    #[LiveAction]
    public function redirectToCatalog(): void
    {
        $this->dispatchBrowserEvent('noho:catalog:update', ['url' => $this->buildListUrl()]);
    }

    #[LiveAction]
    public function setVoyageurs(#[LiveArg] string $value): void
    {
        $this->voyageurs = $this->voyageurs === $value ? '' : $value;

        $this->dispatchBrowserEvent('noho:catalog:update', ['url' => $this->buildListUrl()]);
    }

    #[LiveAction]
    public function setChambres(#[LiveArg] string $value): void
    {
        $this->chambres = $this->chambres === $value ? '' : $value;

        $this->dispatchBrowserEvent('noho:catalog:update', ['url' => $this->buildListUrl()]);
    }

    private function buildListUrl(): string
    {
        $params = $this->queryFromReferer();
        $params['slug'] = $this->slug;
        $params['_locale'] = $this->locale;
        $params['page'] = 1;

        $criteria = $params['criteria'] ?? [];
        if (!\is_array($criteria)) {
            $criteria = [];
        }

        if ('' !== $this->voyageurs) {
            $criteria['voyageurs'] = ['value' => $this->voyageurs];
        } else {
            unset($criteria['voyageurs']);
        }

        if ('' !== $this->chambres) {
            $criteria['chambres'] = ['value' => $this->chambres];
        } else {
            unset($criteria['chambres']);
        }

        if ([] !== $criteria) {
            $params['criteria'] = $criteria;
        } else {
            unset($params['criteria']);
        }

        if ('' !== $this->dateStart) {
            $params['noho_date_start'] = $this->dateStart;
        } else {
            unset($params['noho_date_start']);
        }

        if ('' !== $this->dateEnd) {
            $params['noho_date_end'] = $this->dateEnd;
        } else {
            unset($params['noho_date_end']);
        }

        return $this->urlGenerator->generate(
            'sylius_shop_product_index',
            $params,
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );
    }

    /**
     * Live actions POST to /_components; use the Referer query string so sorting and other filters stay in sync.
     *
     * @return array<string, mixed>
     */
    private function queryFromReferer(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        $referer = $request->headers->get('Referer');
        if (null === $referer || '' === $referer) {
            return [];
        }

        $parts = parse_url($referer);
        if (empty($parts['query']) || !\is_string($parts['query'])) {
            return [];
        }

        $query = [];
        parse_str($parts['query'], $query);

        return \is_array($query) ? $query : [];
    }
}
