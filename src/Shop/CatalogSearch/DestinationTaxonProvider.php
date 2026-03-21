<?php

declare(strict_types=1);

namespace App\Shop\CatalogSearch;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;

/**
 * Builds destination (leaf city) taxon choices under the channel menu taxon.
 */
final class DestinationTaxonProvider
{
    public function __construct(
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
        private TaxonRepositoryInterface $taxonRepository,
    ) {
    }

    /**
     * @return array<string, string> label => full taxon slug (e.g. catalogue/france/paris)
     */
    public function getDestinationChoices(): array
    {
        $channel = $this->channelContext->getChannel();
        $menuTaxon = $channel->getMenuTaxon();
        if (null === $menuTaxon) {
            return [];
        }

        $locale = $this->localeContext->getLocaleCode();
        $menu = $this->taxonRepository->findOneBySlug($menuTaxon->getSlug(), $locale);
        if (null === $menu) {
            return [];
        }

        $choices = [];
        foreach ($menu->getEnabledChildren() as $region) {
            foreach ($region->getEnabledChildren() as $destination) {
                $choices[$destination->getName()] = $destination->getSlug();
            }
        }

        uksort($choices, static fn (string $a, string $b): int => strcasecmp($a, $b));

        return $choices;
    }

    public function isValidDestinationSlug(?string $slug): bool
    {
        if (null === $slug || '' === $slug) {
            return true;
        }

        $locale = $this->localeContext->getLocaleCode();
        $taxon = $this->taxonRepository->findOneBySlug($slug, $locale);
        if (!$taxon instanceof TaxonInterface) {
            return false;
        }

        $channel = $this->channelContext->getChannel();
        $menuTaxon = $channel->getMenuTaxon();
        if (null === $menuTaxon) {
            return false;
        }

        $menu = $this->taxonRepository->findOneBySlug($menuTaxon->getSlug(), $locale);
        if (null === $menu) {
            return false;
        }

        $current = $taxon;
        while (null !== $current) {
            if ($current->getId() === $menu->getId()) {
                return true;
            }
            $current = $current->getParent();
        }

        return false;
    }
}
