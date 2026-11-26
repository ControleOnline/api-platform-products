<?php
namespace ControleOnline\Metadata;

use ApiPlatform\Metadata\{Get, GetCollection, Post, Put, Patch, Delete, Operations};
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ControleOnline\Service\ProductCatalogAccessService;

/** Adds catalog authorization without rewriting entity mappings or serializer contracts. */
final class ProductCatalogResourceMetadataFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(private ResourceMetadataCollectionFactoryInterface $inner, private ProductCatalogAccessService $access) {}
    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->inner->create($resourceClass);
        if (!$this->access->supports($resourceClass)) return $collection;
        foreach ($collection as $index => $resource) {
            $operations = [];
            foreach ($resource->getOperations() ?? [] as $key => $operation) {
                if ($operation instanceof Get || $operation instanceof GetCollection) {
                    $operation = $operation->withSecurity("is_granted('ROLE_HUMAN')");
                } elseif ($operation instanceof Post) {
                    $operation = $operation->withSecurity("is_granted('ROLE_HUMAN')")
                        ->withSecurityPostDenormalize("is_granted('CATALOG_MANAGE', object)");
                } elseif ($operation instanceof Put || $operation instanceof Patch) {
                    $operation = $operation->withSecurity("is_granted('CATALOG_MANAGE', object)")
                        ->withSecurityPostDenormalize("is_granted('CATALOG_MANAGE', object) and is_granted('CATALOG_MANAGE', previous_object)");
                } elseif ($operation instanceof Delete) {
                    $operation = $operation->withSecurity("is_granted('CATALOG_MANAGE', object)");
                }
                $operations[$key] = $operation;
            }
            $collection[$index] = $resource->withOperations(new Operations($operations));
        }
        return $collection;
    }
}
