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
use ControleOnline\Repository\ProductRepository;
use ControleOnline\Repository\OrderRepository;
use Exception;
use Symfony\Component\Security\Http\Attribute\Security;
use ControleOnline\Entity\Product;

class ProductController extends AbstractController
{
    public function __construct(
        private ProductService $productService,
        private ProductMenuService $productMenuService,
        private ProductCatalogNormalizedExportService $productCatalogNormalizedExportService,
        private HydratorService $hydratorService,
        private RequestPayloadService $requestPayloadService,
        private ProductRepository $productRepository,
        private OrderRepository $orderRepository
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

    #[Route('/products/{id}/summary', name: 'product_summary', methods: ['GET'])]
    #[Security("is_granted('ROLE_HUMAN')")]
    public function getProductSummary(int $id, Request $request): JsonResponse
    {
        $query = $request->query->all();
        $orderDate = is_array($query['orderDate'] ?? null) ? $query['orderDate'] : [];

        try {
            $product = $this->productRepository->find($id);
            if (!$product instanceof Product) {
                return new JsonResponse(['error' => 'Produto não encontrado.'], Response::HTTP_NOT_FOUND);
            }

            $summary = $this->orderRepository->resolveProductSalesSummary(
                $product,
                is_string($orderDate['after'] ?? null) ? $orderDate['after'] : null,
                is_string($orderDate['before'] ?? null) ? $orderDate['before'] : null,
            );

            return new JsonResponse([
                'summary' => [
                    'sales' => $summary,
                ],
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (Exception $e) {
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
}
