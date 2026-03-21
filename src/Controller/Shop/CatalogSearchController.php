<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Form\Type\Shop\CatalogSearchType;
use App\Shop\CatalogSearch\DestinationTaxonProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CatalogSearchController extends AbstractController
{
    public function __construct(
        private readonly DestinationTaxonProvider $destinationTaxonProvider,
    ) {
    }

    #[Route(
        path: '/{_locale}/catalogue/search',
        name: 'noho_shop_catalog_search',
        methods: ['POST'],
        requirements: [
            '_locale' => '^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$',
        ],
    )]
    public function __invoke(Request $request): Response
    {
        $form = $this->createForm(CatalogSearchType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', $this->trans('noho.search.invalid'));

            return $this->redirectToRoute('sylius_shop_homepage', ['_locale' => $request->getLocale()]);
        }

        /** @var array{taxonSlug?: string|null, travelers?: string|null, bedrooms?: string|null, dateStart?: string|null, dateEnd?: string|null} $data */
        $data = $form->getData();

        $taxonSlug = $data['taxonSlug'] ?? null;
        if (!$this->destinationTaxonProvider->isValidDestinationSlug(is_string($taxonSlug) ? $taxonSlug : null)) {
            $this->addFlash('error', $this->trans('noho.search.invalid_destination'));

            return $this->redirectToRoute('sylius_shop_homepage', ['_locale' => $request->getLocale()]);
        }

        $slug = (is_string($taxonSlug) && '' !== $taxonSlug) ? $taxonSlug : 'catalogue';

        $query = [
            '_locale' => $request->getLocale(),
            'slug' => $slug,
        ];

        $travelers = $data['travelers'] ?? null;
        if (is_string($travelers) && '' !== $travelers) {
            $query['criteria']['voyageurs']['value'] = $travelers;
        }

        $bedrooms = $data['bedrooms'] ?? null;
        if (is_string($bedrooms) && '' !== $bedrooms) {
            $query['criteria']['chambres']['value'] = $bedrooms;
        }

        $dateStart = $data['dateStart'] ?? null;
        if (is_string($dateStart) && '' !== $dateStart && $this->isIsoDate($dateStart)) {
            $query['noho_date_start'] = $dateStart;
        }

        $dateEnd = $data['dateEnd'] ?? null;
        if (is_string($dateEnd) && '' !== $dateEnd && $this->isIsoDate($dateEnd)) {
            $query['noho_date_end'] = $dateEnd;
        }

        $url = $this->generateUrl('sylius_shop_product_index', $query, UrlGeneratorInterface::ABSOLUTE_PATH);

        return $this->redirect($url);
    }

    private function isIsoDate(string $value): bool
    {
        return 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}
