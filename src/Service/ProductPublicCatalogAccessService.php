<?php
namespace ControleOnline\Service;

use ControleOnline\Entity\{Config, People, PeopleDomain};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Public shop projection follows the same company allowlist as public shop categories. */
class ProductPublicCatalogAccessService
{
    public function __construct(private ProductCatalogAccessService $access, private DomainService $domain, private PublicShopCategoryService $shopCategories, private EntityManagerInterface $manager) {}

    public function assertCatalog(People $company, string $integrationKey): void
    {
        try { $this->access->assertReadCompany($company); return; }
        catch (AccessDeniedException) {}
        if (strtolower(trim($integrationKey)) !== 'shop') throw new AccessDeniedException('Operational catalogs require company access.');
        $this->assertPublicShopCompany($company);
    }

    public function assertPublicShopCompany(People $company): void
    {
        try {
            // Exact lookup: DomainService::getPeopleDomain() may fall back to the main domain.
            $domain = $this->manager->getRepository(PeopleDomain::class)->findOneBy(['domain' => $this->domain->getDomain()]);
        } catch (\Throwable) { throw new AccessDeniedException('A registered shop domain is required.'); }
        if (!$domain instanceof PeopleDomain || strtoupper(trim((string) $domain->getDomainType())) !== 'SHOP' || !$domain->getPeople()?->getEnabled()) {
            throw new AccessDeniedException('A registered shop domain is required.');
        }
        $allowedCompany = $this->shopCategories->resolvePublicShopCompanyId((int) $company->getId());
        if (!$company->getEnabled() || $allowedCompany !== (int) $company->getId()) throw new AccessDeniedException('Company is not published by this shop.');
    }
}
