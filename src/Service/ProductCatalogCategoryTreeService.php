<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\Category;
use ControleOnline\Entity\People;
use ControleOnline\Repository\CategoryRepository;

class ProductCatalogCategoryTreeService
{
    private const CATEGORY_CONTEXT = 'products';

    public function __construct(
        private CategoryRepository $categoryRepository,
        private CategoryTreeService $categoryTreeService,
        private CategoryPayloadService $categoryPayloadService
    ) {
    }

    /**
     * Composes Products' authoritative publication projection with Common's
     * generic hierarchy. The IDs must come from ProductCatalogProjectionService.
     *
     * @param int[] $publishedCategoryIds
     *
     * @return array<int, array<string, mixed>>
     */
    public function build(People $company, array $publishedCategoryIds): array
    {
        $companyId = (int) $company->getId();
        if ($companyId <= 0) {
            return [];
        }

        $tree = $this->categoryTreeService->build(
            $this->categoryRepository->findTreeCandidates($companyId, self::CATEGORY_CONTEXT),
            $companyId,
            self::CATEGORY_CONTEXT,
            $publishedCategoryIds,
            '',
            false,
            1,
            PHP_INT_MAX
        );

        return array_map(
            fn (Category $category): array => $this->categoryPayloadService->serialize($category),
            $tree['items']
        );
    }
}
