<?php

declare(strict_types=1);

namespace App\Grid\Filter;

use App\Entity\Product\ProductAttributeValue;
use App\Form\Type\Filter\ProductAttributeMinimumFilterType;
use Sylius\Bundle\GridBundle\Doctrine\DataSourceInterface;
use Sylius\Component\Grid\Attribute\AsFilter;
use Sylius\Component\Grid\Data\DataSourceInterface as GridDataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;

/**
 * Restricts products that have an integer attribute >= submitted minimum (capacity-style filter).
 */
#[AsFilter(formType: ProductAttributeMinimumFilterType::class, type: 'noho_product_attribute_minimum', template: 'bundles/SyliusShopBundle/grid/filter/hidden_value.html.twig')]
final class ProductAttributeMinimumFilter implements FilterInterface
{
    public function apply(GridDataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        if (!$dataSource instanceof DataSourceInterface) {
            return;
        }

        $attributeCode = $options['attribute_code'] ?? null;
        if (!is_string($attributeCode) || '' === $attributeCode) {
            return;
        }

        $raw = \is_array($data) ? ($data['value'] ?? null) : $data;
        if (null === $raw || '' === $raw) {
            return;
        }

        $min = $this->parseMinimum($raw);
        if (null === $min) {
            return;
        }

        $qb = $dataSource->getQueryBuilder();
        $rootAlias = $qb->getRootAliases()[0] ?? 'o';

        $pavAlias = 'noho_pav_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $attrAlias = 'noho_attr_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        $paramCode = 'noho_ac_' . $name;
        $paramMin = 'noho_am_' . $name;

        $dql = sprintf(
            'SELECT 1 FROM %s %s INNER JOIN %s.attribute %s WHERE %s.subject = %s AND %s.code = :%s AND %s.integer >= :%s',
            ProductAttributeValue::class,
            $pavAlias,
            $pavAlias,
            $attrAlias,
            $pavAlias,
            $rootAlias,
            $attrAlias,
            $paramCode,
            $pavAlias,
            $paramMin,
        );

        $qb->andWhere($qb->expr()->exists($dql));
        $qb->setParameter($paramCode, $attributeCode);
        $qb->setParameter($paramMin, $min);
    }

    private function parseMinimum(mixed $raw): ?int
    {
        if (\is_int($raw)) {
            return $raw;
        }

        $s = trim((string) $raw);
        if ('' === $s) {
            return null;
        }

        if (str_ends_with($s, '+')) {
            $s = rtrim($s, '+');
        }

        if (!is_numeric($s)) {
            return null;
        }

        return (int) $s;
    }
}
