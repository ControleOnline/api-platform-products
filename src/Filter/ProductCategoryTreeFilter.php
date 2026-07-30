<?php

namespace ControleOnline\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;

final class ProductCategoryTreeFilter extends AbstractFilter
{
    private const PROPERTY = 'productCategory.category';

    protected function filterProperty(
        string $property,
        mixed $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = [],
    ): void {
        $normalizedProperty = rtrim($property, '[]');
        if ($normalizedProperty !== self::PROPERTY) {
            return;
        }

        $categoryIds = $this->resolveCategoryTreeIds($resourceClass, $value);
        if ($categoryIds === []) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $joinAlias = $queryNameGenerator->generateJoinAlias('productCategoryTree');
        $parameterName = $queryNameGenerator->generateParameterName('productCategoryTree');

        $queryBuilder
            ->distinct()
            ->join(sprintf('%s.productCategory', $rootAlias), $joinAlias)
            ->andWhere(sprintf('IDENTITY(%s.category) IN (:%s)', $joinAlias, $parameterName))
            ->setParameter($parameterName, $categoryIds, ArrayParameterType::INTEGER);
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            self::PROPERTY => [
                'property' => self::PROPERTY,
                'type' => 'string',
                'required' => false,
                'openapi' => [
                    'description' => 'Filter products by category including descendant categories.',
                ],
            ],
        ];
    }

    /**
     * @return int[]
     */
    private function resolveCategoryTreeIds(string $resourceClass, mixed $value): array
    {
        $ids = [];
        foreach ($this->normalizeCategoryFilterValues($value) as $rawValue) {
            $categoryId = $this->extractId($rawValue);
            if ($categoryId > 0) {
                $ids[] = $categoryId;
            }
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }

        $connection = $this
            ->getManagerRegistry()
            ->getManagerForClass($resourceClass)
            ?->getConnection();

        if ($connection === null) {
            return $ids;
        }

        $resolvedIds = $ids;
        $frontier = $ids;

        while ($frontier !== []) {
            $children = $connection->fetchFirstColumn(
                'SELECT id FROM category WHERE parent_id IN (:parentIds)',
                ['parentIds' => $frontier],
                ['parentIds' => ArrayParameterType::INTEGER],
            );

            $children = array_values(array_diff(array_map('intval', $children), $resolvedIds));
            if ($children === []) {
                break;
            }

            $resolvedIds = array_merge($resolvedIds, $children);
            $frontier = $children;
        }

        return array_values(array_unique($resolvedIds));
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeCategoryFilterValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        return [$value];
    }

    private function extractId(mixed $value): int
    {
        if (is_object($value) && method_exists($value, 'getId')) {
            return (int) $value->getId();
        }

        return (int) preg_replace('/\D+/', '', (string) $value);
    }
}
