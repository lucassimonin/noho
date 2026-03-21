<?php

declare(strict_types=1);

namespace App\Grid\Filter;

use App\Entity\Product\ProductVariant;
use App\Form\Type\Filter\ChannelPriceBucketFilterType;
use Sylius\Bundle\GridBundle\Doctrine\DataSourceInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Grid\Attribute\AsFilter;
use Sylius\Component\Grid\Data\DataSourceInterface as GridDataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;
use Symfony\Component\Intl\Currencies;

/**
 * Filters products by minimum variant channel price in the current channel (bucket labels are in major currency units).
 */
#[AsFilter(formType: ChannelPriceBucketFilterType::class, type: 'noho_channel_price_bucket', template: 'bundles/SyliusShopBundle/grid/filter/hidden_value.html.twig')]
final class ChannelPriceBucketFilter implements FilterInterface
{
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
    ) {
    }

    public function apply(GridDataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        if (!$dataSource instanceof DataSourceInterface) {
            return;
        }

        $raw = \is_array($data) ? ($data['value'] ?? null) : $data;
        if (null === $raw || '' === $raw) {
            return;
        }

        $bucket = (string) $raw;
        $channel = $this->channelContext->getChannel();
        $currency = $channel->getBaseCurrency();
        $currencyCode = $currency?->getCode();
        if (null === $currencyCode || '' === $currencyCode) {
            return;
        }

        $fractionDigits = Currencies::getFractionDigits($currencyCode);
        $factor = 10 ** $fractionDigits;

        [$minMinor, $maxMinor] = $this->parseBucket($bucket, $factor);
        if (null === $minMinor) {
            return;
        }

        $qb = $dataSource->getQueryBuilder();
        $rootAlias = $qb->getRootAliases()[0] ?? 'o';
        $channelCode = $channel->getCode();
        if (null === $channelCode || '' === $channelCode) {
            return;
        }

        $vAlias = 'noho_price_v_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $cpAlias = 'noho_price_cp_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $pCh = 'noho_pch_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $pMin = 'noho_pmin_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $pMax = 'noho_pmax_' . preg_replace('/[^a-z0-9_]/i', '_', $name);

        $dql = sprintf(
            'SELECT 1 FROM %s %s INNER JOIN %s.channelPricings %s WHERE %s.product = %s AND %s.enabled = true AND %s.channelCode = :%s AND %s.price IS NOT NULL AND %s.price >= :%s AND %s.price <= :%s',
            ProductVariant::class,
            $vAlias,
            $vAlias,
            $cpAlias,
            $vAlias,
            $rootAlias,
            $vAlias,
            $cpAlias,
            $pCh,
            $cpAlias,
            $cpAlias,
            $pMin,
            $cpAlias,
            $pMax,
        );

        $qb->andWhere($qb->expr()->exists($dql));
        $qb->setParameter($pCh, $channelCode);
        $qb->setParameter($pMin, $minMinor);
        $qb->setParameter($pMax, $maxMinor);
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function parseBucket(string $bucket, int|float $factor): ?array
    {
        $bucket = trim($bucket);
        $maxInt = 2_147_000_000;

        // Prefer _plus in URLs: "+" is decoded as space in x-www-form-urlencoded and breaks "2000+".
        if (str_ends_with($bucket, '_plus')) {
            $n = (int) substr($bucket, 0, -5);
            if ($n < 0) {
                return null;
            }

            return [(int) round($n * $factor), $maxInt];
        }

        if (str_ends_with($bucket, '+')) {
            $n = (int) rtrim($bucket, '+');
            if ($n < 0) {
                return null;
            }

            return [(int) round($n * $factor), $maxInt];
        }

        if (!str_contains($bucket, '-')) {
            return null;
        }

        [$a, $b] = array_map('trim', explode('-', $bucket, 2));
        if (!is_numeric($a) || !is_numeric($b)) {
            return null;
        }

        $minMajor = (float) $a;
        $maxMajor = (float) $b;
        if ($minMajor > $maxMajor) {
            return null;
        }

        return [
            (int) round($minMajor * $factor),
            (int) round($maxMajor * $factor),
        ];
    }
}
