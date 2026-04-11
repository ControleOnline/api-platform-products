<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Device;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use ControleOnline\Entity\People;
use ControleOnline\Entity\Spool;
use ControleOnline\Service\HydratorService;
use ControleOnline\Service\ProductMenuService;
use ControleOnline\Service\ProductService;
use Exception;
use Symfony\Component\Security\Http\Attribute\Security;
use ControleOnline\Entity\Product;

class ProductController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private ProductService $productService,
        private ProductMenuService $productMenuService,
        private HydratorService $hydratorService

    ) {}

    #[Route('/products/purchasing-suggestion', name: 'purchasing_suggestion', methods: ['GET'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function getPurchasingSuggestion(Request $request): JsonResponse
    {
        $company =  $this->manager->getRepository(People::class)->find($request->get('company'));
        $data = $this->productService->getPurchasingSuggestion($company);
        return new JsonResponse($data);
    }

    #[Route('/products/purchasing-suggestion/print', name: 'purchasing_suggestion_print', methods: ['POST'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function printPurchasingSuggestion(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $device = $this->manager->getRepository(Device::class)->findOneBy([
                'device' => $data['device']
            ]);
            $company = $this->manager->getRepository(People::class)->find($data['people']);
            $printData = $this->productService->purchasingSuggestionPrintData($company, $device);

            return new JsonResponse($this->hydratorService->item(Spool::class, $printData->getId(), "spool_item:read"), Response::HTTP_OK);
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }

    #[Route('/products/inventory', name: 'products_inventory', methods: ['GET'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function getProductsInventory(Request $request): JsonResponse
    {
        $company =  $this->manager->getRepository(People::class)->find($request->get('company'));
        $data = $this->productService->getProductsInventory($company);
        return new JsonResponse($data);
    }

    #[Route('/products/inventory/print', name: 'products_inventory_print', methods: ['POST'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function print(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $device = $this->manager->getRepository(Device::class)->findOneBy([
                'device' => $data['device']
            ]);

            $company = $this->manager->getRepository(People::class)->find($data['people']);
            $printData = $this->productService->productsInventoryPrintData($company, $device);
            return new JsonResponse($this->hydratorService->item(Spool::class, $printData->getId(), "spool_item:read"), Response::HTTP_OK);
        } catch (Exception $e) {
            return new JsonResponse($this->hydratorService->error($e));
        }
    }

    #[Route('/products/sku', name: 'product_by_sku', methods: ['POST'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function getProductBySku(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['sku'], $data['people'])) {
                return new JsonResponse(['error' => 'Parâmetros obrigatórios: sku e people'], Response::HTTP_BAD_REQUEST);
            }

            $sku = (int) ltrim($data['sku'], '0');
            $company = $this->manager->getRepository(People::class)->find($data['people']);

            if (!$company) {
                return new JsonResponse(['error' => 'Empresa não encontrada'], Response::HTTP_NOT_FOUND);
            }

            $product = $this->manager
                ->getRepository(Product::class)
                ->findProductBySkuAsInteger($sku, $company);

            if (!$product) {
                return new JsonResponse(['error' => 'Produto não encontrado'], Response::HTTP_NOT_FOUND);
            }

            return new JsonResponse(
                $this->hydratorService->item(Product::class, $product->getId(), 'product:read'),
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/products/menu/download', name: 'product_menu_download', methods: ['GET'])]
    #[Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")]
    public function downloadMenuCatalog(Request $request): Response
    {
        $companyId = (int) preg_replace('/\D+/', '', (string) $request->get('company'));
        $modelId = (int) preg_replace('/\D+/', '', (string) $request->get('model'));

        if ($companyId <= 0) {
            return new JsonResponse(
                ['error' => 'Parametro obrigatorio: company'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $company = $this->manager->getRepository(People::class)->find($companyId);

        if (!$company instanceof People) {
            return new JsonResponse(['error' => 'Empresa nao encontrada'], Response::HTTP_NOT_FOUND);
        }

        try {
            $pdf = $this->productMenuService->generateCatalogPdf(
                $company,
                $modelId > 0 ? $modelId : null
            );
            $filename = $this->buildCatalogFilename($company);
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

    #[Route('/teste', name: 'teste', methods: ['GET'])]
    public function teste(): JsonResponse
    {
        return new JsonResponse(['message' => 'rota funcionando']);
    }

    private function buildCatalogFilename(People $company): string
    {
        $baseName = trim((string) ($company->getAlias() ?: $company->getName()));
        $slug = strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $baseName));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug ?? '');
        $slug = trim((string) $slug, '-');

        return sprintf('cardapio-%s.pdf', $slug !== '' ? $slug : $company->getId());
    }
}
