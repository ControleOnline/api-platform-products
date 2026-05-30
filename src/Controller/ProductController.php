<?php

namespace ControleOnline\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\Routing\Attribute\Route;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Spool;
use ControleOnline\Service\ProductCatalogNormalizedExportService;
use ControleOnline\Service\HydratorService;
use ControleOnline\Service\ProductMenuService;
use ControleOnline\Service\RequestPayloadService;
use ControleOnline\Service\ProductService;
use ControleOnline\Repository\ProductCategoryRepository;
use ControleOnline\Repository\ProductGroupRepository;
use Exception;
use Symfony\Component\Security\Http\Attribute\Security;
use ControleOnline\Entity\Product;

class ProductController extends AbstractController
{
    private const SHOP_CATALOG_DEFAULT_PRODUCT_TYPES = ['product', 'manufactured', 'custom', 'service'];
    private const SHOP_CATALOG_GROUP_MODIFIER_TYPES = ['component', 'package'];

    public function __construct(
        private ProductService $productService,
        private ProductMenuService $productMenuService,
        private ProductCatalogNormalizedExportService $productCatalogNormalizedExportService,
        private HydratorService $hydratorService,
        private RequestPayloadService $requestPayloadService,
        private ProductCategoryRepository $productCategoryRepository,
        private ProductGroupRepository $productGroupRepository

    ) {}

    #[Route('/products/purchasing-suggestion', name: 'purchasing_suggestion', methods: ['GET'])]
    #[Security("is_granted('ROLE_HUMAN')")]
    public function getPurchasingSuggestion(Request $request): JsonResponse
    {
        $company = $this->productService->resolveCompanyReference($request->get('company'));
        if (!$company instanceof People) {
            return new JsonResponse(['error' => 'Empresa não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $data = $this->productService->getPurchasingSuggestion($company);
        return new JsonResponse($data);
    }

    #[Route('/products/purchasing-suggestion/print', name: 'purchasing_suggestion_print', methods: ['POST'])]
    #[Security("is_granted('ROLE_HUMAN')")]
    public function printPurchasingSuggestion(Request $request): JsonResponse
    {
        try {
            $printData = $this->productService->printPurchasingSuggestionFromContent(
                $request->getContent()
            );

            return new JsonResponse($this->hydratorService->item(Spool::class, $printData->getId(), "spool_item:read"), Response::HTTP_OK);
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }

    #[Route('/products/inventory', name: 'products_inventory', methods: ['GET'])]
    #[Security("is_granted('ROLE_HUMAN')")]
    public function getProductsInventory(Request $request): JsonResponse
    {
        $company = $this->productService->resolveCompanyReference($request->get('company'));
        if (!$company instanceof People) {
            return new JsonResponse(['error' => 'Empresa não encontrada'], Response::HTTP_NOT_FOUND);
        }

        $data = $this->productService->getProductsInventory($company);
        return new JsonResponse($data);
    }

    #[Route('/products/labels/print', name: 'products_labels_print', methods: ['POST'])]
    #[Security("is_granted('ROLE_HUMAN')")]
    public function printLabel(Request $request): JsonResponse
    {
        try {
            $printData = $this->productService->printProductLabelFromContent(
                $request->getContent()
            );

            return new JsonResponse($this->hydratorService->item(Spool::class, $printData->getId(), "spool_item:read"), Response::HTTP_OK);
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }

    #[Route('/products/inventory/print', name: 'products_inventory_print', methods: ['POST'])]
    #[Security("is_granted('ROLE_HUMAN')")]
    public function print(Request $request): JsonResponse
    {
        try {
            $printData = $this->productService->printProductsInventoryFromContent(
                $request->getContent()
            );
            return new JsonResponse($this->hydratorService->item(Spool::class, $printData->getId(), "spool_item:read"), Response::HTTP_OK);
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }

    #[Route('/products/sku', name: 'product_by_sku', methods: ['POST'])]
    #[Security("is_granted('ROLE_HUMAN')")]
    public function getProductBySku(Request $request): JsonResponse
    {
        try {
            $product = $this->productService->findProductBySkuFromContent(
                $request->getContent()
            );

            return new JsonResponse(
                $this->hydratorService->item(Product::class, $product->getId(), 'product:read'),
                Response::HTTP_OK
            );
        } catch (\Symfony\Component\HttpKernel\Exception\BadRequestHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/products/menu/download', name: 'product_menu_download', methods: ['GET'])]
    #[Security("is_granted('PUBLIC_ACCESS')")]
    public function downloadMenuCatalog(Request $request): Response
    {
        $companyReference = trim((string) $request->get('company'));
        $modelId = $this->requestPayloadService->normalizeOptionalNumericId(
            $request->get('model')
        );

        if ($companyReference === '') {
            return new JsonResponse(
                ['error' => 'Parametro obrigatorio: company'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $company = $this->productService->resolveCompanyReference($companyReference);

        if (!$company instanceof People) {
            return new JsonResponse(['error' => 'Empresa nao encontrada'], Response::HTTP_NOT_FOUND);
        }

        try {
            $pdf = $this->productMenuService->generateCatalogPdf(
                $company,
                $modelId
            );
            $filename = $this->productMenuService->buildCatalogFilename($company);
            $response = new Response($pdf, Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
            ]);

            $response->headers->set(
                'Content-Disposition',
                HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename)
            );

            return $response;
        } catch (\Throwable $exception) {
            return new JsonResponse(
                ['error' => $exception->getMessage()],
                $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    ? $exception->getStatusCode()
                    : Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/products/shop-catalog', name: 'products_shop_catalog', methods: ['GET'])]
    #[Security("is_granted('PUBLIC_ACCESS')")]
    public function getShopCatalog(Request $request): JsonResponse
    {
        $companyReference = trim((string) $request->get('company'));
        $context = trim((string) ($request->get('context') ?: 'products'));

        if ($companyReference === '') {
            return new JsonResponse(
                ['error' => 'Parametro obrigatorio: company'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $company = $this->productService->resolveCompanyReference($companyReference);

        if (!$company instanceof People) {
            return new JsonResponse(['error' => 'Empresa nao encontrada'], Response::HTTP_NOT_FOUND);
        }

        $productTypes = $this->normalizeShopCatalogProductTypes($request);
        $productCategories = $this->productCategoryRepository->findVisibleForMenuCatalog(
            $company,
            $context !== '' ? $context : 'products',
            $productTypes
        );

        $productsById = [];
        foreach ($productCategories as $productCategory) {
            $product = $productCategory->getProduct();
            if ($product instanceof Product) {
                $productsById[(int) $product->getId()] = $product;
            }
        }

        $customizationProductIds = $this->resolveShopCatalogCustomizationProductIds(
            $this->productGroupRepository->findVisibleComponentGroupsForMenuCatalog(
                array_values($productsById),
                self::SHOP_CATALOG_GROUP_MODIFIER_TYPES
            )
        );

        $categoriesById = [];
        $productsByCategoryId = [];

        foreach ($productCategories as $productCategory) {
            $category = $productCategory->getCategory();
            $product = $productCategory->getProduct();
            $categoryId = (string) $category->getId();
            $productId = (int) $product->getId();

            if (!isset($categoriesById[$categoryId])) {
                $categoryPayload = $this->hydratorService->data($category, 'category:read');
                $categoryPayload->productCount = 0;
                $categoriesById[$categoryId] = $categoryPayload;
                $productsByCategoryId[$categoryId] = [];
            }

            $productPayload = $this->hydratorService->data($product, 'product:read');
            $productPayload->{'@id'} = $productPayload->{'@id'} ?? '/products/' . $productId;
            $productPayload->hasCustomizationGroups = isset($customizationProductIds[$productId]);
            $productPayload->customizationGroupsLoaded = true;

            $productsByCategoryId[$categoryId][] = $productPayload;
            $categoriesById[$categoryId]->productCount++;
        }

        return new JsonResponse([
            '@context' => '/contexts/ShopCatalog',
            '@id' => '/products/shop-catalog',
            '@type' => 'ShopCatalog',
            'categories' => array_values($categoriesById),
            'productsByCategoryId' => $productsByCategoryId,
            'categoryCount' => count($categoriesById),
            'productCount' => count($productsById),
        ]);
    }

    #[Route('/products/catalog/download-normalized', name: 'product_catalog_download_normalized', methods: ['GET'])]
    #[Security("is_granted('ROLE_HUMAN')")]
    public function downloadNormalizedCatalog(Request $request): Response
    {
        $companyReference = trim((string) $request->get('company'));
        $context = trim((string) $request->get('context'));

        if ($companyReference === '') {
            return new JsonResponse(
                ['error' => 'Parametro obrigatorio: company'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $company = $this->productService->resolveCompanyReference($companyReference);

        if (!$company instanceof People) {
            return new JsonResponse(['error' => 'Empresa nao encontrada'], Response::HTTP_NOT_FOUND);
        }

        try {
            $csv = $this->productCatalogNormalizedExportService->buildNormalizedCatalogCsv(
                $company,
                $context
            );
            $filename = $this->productCatalogNormalizedExportService->buildNormalizedCatalogFilename($company);
            $response = new Response($csv, Response::HTTP_OK, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);

            $response->headers->set(
                'Content-Disposition',
                HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename)
            );

            return $response;
        } catch (\Throwable $exception) {
            return new JsonResponse(
                ['error' => $exception->getMessage()],
                $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    ? $exception->getStatusCode()
                    : Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/teste', name: 'teste', methods: ['GET'])]
    public function teste(): JsonResponse
    {
        return new JsonResponse(['message' => 'rota funcionando']);
    }

    private function normalizeShopCatalogProductTypes(Request $request): array
    {
        $query = $request->query->all();
        $rawTypes = $query['type'] ?? self::SHOP_CATALOG_DEFAULT_PRODUCT_TYPES;

        if (!is_array($rawTypes)) {
            $rawTypes = explode(',', (string) $rawTypes);
        }

        $types = array_values(array_filter(
            array_map(static fn($type): string => trim((string) $type), $rawTypes),
            static fn(string $type): bool => $type !== ''
        ));

        return $types ?: self::SHOP_CATALOG_DEFAULT_PRODUCT_TYPES;
    }

    private function resolveShopCatalogCustomizationProductIds(array $groups): array
    {
        $productIds = [];

        foreach ($groups as $group) {
            foreach ($group->getParentProducts() as $groupParent) {
                if (method_exists($groupParent, 'isActive') && !$groupParent->isActive()) {
                    continue;
                }

                $parentProduct = $groupParent->getParentProduct();
                if ($parentProduct instanceof Product) {
                    $productIds[(int) $parentProduct->getId()] = true;
                }
            }
        }

        return $productIds;
    }
}
