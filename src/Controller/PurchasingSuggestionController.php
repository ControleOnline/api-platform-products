<?php

namespace ControleOnline\Controller;

use ControleOnline\Entity\Device;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Doctrine\ORM\EntityManagerInterface;
use ControleOnline\Entity\People;
use ControleOnline\Service\ProductService;

class PurchasingSuggestionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $manager,
        private ProductService $productService
    ) {}

    /**
     * @Route("/products/purchasing-suggestion", name="purchasing_suggestion", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")
     */
    public function getPurchasingSuggestion(Request $request): JsonResponse
    {
        $company =  $this->manager->getRepository(People::class)->find($request->get('company'));
        $data = $this->productService->getPurchasingSuggestion($company);
        return new JsonResponse($data);
    }

    /**
     * @Route("/products/purchasing-suggestion/print", name="purchasing_suggestion_print", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_CLIENT')")
     */
    public function printPurchasingSuggestion(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $printType = $data['print-type'];
        $deviceType = $data['device-type'];
        $company = $this->manager->getRepository(People::class)->find($data['people']);
        $printData = $this->productService->purchasingSuggestionPrintData($company, $printType, $deviceType);
        return new JsonResponse($printData);
    }
}
