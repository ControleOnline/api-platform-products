<?php

namespace ControleOnline\Service;

use ControleOnline\Entity\{People, PeopleLink, Product, ProductCategory, ProductFile, ProductGroup, ProductGroupParent, ProductGroupProduct, ProductInventory, ProductPeople, ProductShowcase, ProductShowcaseItem, Inventory};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Catalog rights are direct, active, company-specific PeopleLink grants. */
class ProductCatalogAccessService
{
    public const COMPANY_PATHS = [
        Product::class => ['company'], ProductGroup::class => ['company'],
        ProductPeople::class => ['product.company'], ProductCategory::class => ['product.company', 'category.company'],
        ProductFile::class => ['product.company', 'file.people'], ProductGroupParent::class => ['productGroup.company', 'parentProduct.company'],
        ProductGroupProduct::class => ['productGroup.company', 'productChild.company'],
        Inventory::class => ['people'], ProductInventory::class => ['product.company', 'inventory.people'],
        ProductShowcase::class => ['company'], ProductShowcaseItem::class => ['showcase.company', 'product.company'],
    ];

    public function __construct(private PeopleRoleService $roles, private EntityManagerInterface $manager) {}

    public function supports(object|string $entity): bool
    {
        foreach (array_keys(self::COMPANY_PATHS) as $class) {
            if (is_object($entity) ? $entity instanceof $class : $entity === $class) return true;
        }
        return false;
    }

    public function assertReadCompany(?People $company): void
    {
        if (!$company || !$this->roles->canAccessCompany($company, null, PeopleLink::HUMAN_LINK)) {
            throw new AccessDeniedException('Catalog company is not accessible.');
        }
    }

    public function assertManageCompany(?People $company): void
    {
        if (!$company || !$this->roles->canAccessCompany($company, null, PeopleLink::ADMIN_LINK)) {
            throw new AccessDeniedException('A company catalog management grant is required.');
        }
    }

    public function filter(QueryBuilder $query, string $class, ?string $root = null): void
    {
        if (!isset(self::COMPANY_PATHS[$class])) return;
        $root ??= $query->getRootAliases()[0];
        $companies = array_map(static fn(People $company) => $company->getId(), $this->roles->getAccessibleCompaniesForPeople(null, PeopleLink::HUMAN_LINK));
        if (!$companies) { $query->andWhere('1 = 0'); return; }
        foreach (self::COMPANY_PATHS[$class] as $index => $path) {
            $parts = explode('.', $path); $alias = $root;
            while (count($parts) > 1) {
                $property = array_shift($parts);
                $joined = sprintf('catalog_scope_%d_%s', $index, $property);
                if (!in_array($joined, $query->getAllAliases(), true)) $query->innerJoin($alias.'.'.$property, $joined);
                $alias = $joined;
            }
            $parameter = 'catalog_companies_'.$index;
            $query->andWhere($alias.'.'.$parts[0].' IN (:'.$parameter.')')->setParameter($parameter, $companies);
        }
    }

    /** Authorize the persisted owner and the proposed owner; changing a reference cannot steal a resource. */
    public function assertWrite(object $entity, array $original = []): void
    {
        if (!$this->supports($entity)) return;
        if ($entity instanceof Product && isset($original['company']) && !$this->sameCompany($entity->getCompany(), $original['company'])) {
            throw new AccessDeniedException('Catalog company transfer requires a separate migration.');
        }
        $this->assertCurrentWrite($entity);
        if ($original) {
            $previous = clone $entity;
            foreach ($original as $field => $value) {
                $reflection = new \ReflectionObject($previous);
                while ($reflection && !$reflection->hasProperty($field)) $reflection = $reflection->getParentClass();
                if ($reflection) $reflection->getProperty($field)->setValue($previous, $value);
            }
            $this->assertCurrentWrite($previous);
        }
    }

    private function assertCurrentWrite(object $entity): void
    {
        $companies = [];
        foreach (self::COMPANY_PATHS as $class => $paths) {
            if (!$entity instanceof $class) continue;
            foreach ($paths as $path) {
                $value = $entity;
                foreach (explode('.', $path) as $property) $value = $value?->{'get'.ucfirst($property)}();
                $this->assertManageCompany($value);
                $companies[] = $value;
            }
            break;
        }
        // Related catalog objects must belong to one company, even for managers of multiple companies.
        foreach ($companies as $company) {
            if (!$this->sameCompany($company, $companies[0])) {
                throw new AccessDeniedException('Catalog associations must share a company.');
            }
        }
        $owner = $companies[0] ?? null;
        $optional = [];
        if ($entity instanceof Product) $optional = [$entity->getDefaultInInventory()?->getPeople(), $entity->getDefaultOutInventory()?->getPeople(), $entity->getQueue()?->getCompany()];
        if ($entity instanceof ProductShowcaseItem) $optional[] = $entity->getOutInventory()?->getPeople();
        if ($entity instanceof ProductShowcase && $entity->getPeopleDomain()) $optional[] = $entity->getPeopleDomain()->getPeople();
        if ($entity instanceof ProductFile) $optional[] = $entity->getFile()->getPeople();
        foreach ($optional as $company) {
            if ($company && !$this->sameCompany($company, $owner)) throw new AccessDeniedException('Catalog references must belong to the same company.');
        }
        if ($entity instanceof ProductPeople) {
            $supplier = $entity->getPeople();
            $link = $supplier ? $this->manager->getRepository(PeopleLink::class)->findOneBy([
                'company' => $entity->getProduct()?->getCompany(), 'people' => $supplier,
                'linkType' => 'provider', 'enable' => true,
            ]) : null;
            if (!$supplier?->getEnabled() || !$link instanceof PeopleLink || !$link->getEnabled()) {
                throw new AccessDeniedException('An enabled company provider relationship is required.');
            }
        }
        if ($entity instanceof ProductGroupProduct && $entity->getProduct()) {
            $parentCompany = $entity->getProduct()->getCompany();
            $this->assertManageCompany($parentCompany);
            if (!$this->sameCompany($parentCompany, $entity->getProductGroup()?->getCompany())) {
                throw new AccessDeniedException('Recipe parent must belong to the group company.');
            }
        }
    }

    private function sameCompany(?People $left, ?People $right): bool
    {
        return $left && $right && ($left === $right || ((int) $left->getId() > 0 && $left->getId() === $right->getId()));
    }
}
