<?php

declare(strict_types=1);

namespace App\Twig;

use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TaxonExtension extends AbstractExtension
{
    public function __construct(
        private RequestStack $requestStack,
        private TaxonRepositoryInterface $taxonRepository,
        private LocaleContextInterface $localeContext,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('noho_current_taxon', [$this, 'getCurrentTaxon']),
            new TwigFunction('noho_taxon_ancestors', [$this, 'getTaxonAncestors']),
        ];
    }

    public function getCurrentTaxon(): ?TaxonInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (null === $request) {
            return null;
        }

        $slug = $request->attributes->get('slug');
        
        if (null === $slug) {
            return null;
        }

        return $this->taxonRepository->findOneBySlug($slug, $this->localeContext->getLocaleCode());
    }

    /**
     * Get all ancestors of a taxon (excluding root), from parent to root
     * 
     * @return TaxonInterface[]
     */
    public function getTaxonAncestors(?TaxonInterface $taxon): array
    {
        if (null === $taxon) {
            return [];
        }

        $ancestors = [];
        $parent = $taxon->getParent();

        while (null !== $parent) {
            $ancestors[] = $parent;
            $parent = $parent->getParent();
        }

        return array_reverse($ancestors);
    }
}
