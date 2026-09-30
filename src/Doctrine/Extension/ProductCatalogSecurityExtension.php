<?php
namespace ControleOnline\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\{QueryCollectionExtensionInterface, QueryItemExtensionInterface};
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ControleOnline\Service\ProductCatalogAccessService;
use Doctrine\ORM\QueryBuilder;

final class ProductCatalogSecurityExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private ProductCatalogAccessService $access) {}
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    { $this->access->filter($queryBuilder, $resourceClass); }
    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    { $this->access->filter($queryBuilder, $resourceClass); }
}
