<?php

namespace ControleOnline\Products\Tests\Security;

use ControleOnline\Security\ProductAccessPolicy;
use PHPUnit\Framework\TestCase;

class ProductAccessPolicyTest extends TestCase
{
    public function testCompanyOwnerCanReadOwnCatalog(): void
    {
        $policy = new ProductAccessPolicy();

        self::assertTrue($policy->canReadCompany(12, 12, []));
    }

    public function testEmployeeCanReadAccessibleCompanyCatalog(): void
    {
        $policy = new ProductAccessPolicy();

        self::assertTrue($policy->canReadCompany(18, 7, [18, 22, 18]));
    }

    public function testUnauthorizedPeopleCannotReadForeignCatalog(): void
    {
        $policy = new ProductAccessPolicy();

        self::assertFalse($policy->canReadCompany(18, 7, [22]));
    }

    public function testOnlyManagedCompaniesCanBeChanged(): void
    {
        $policy = new ProductAccessPolicy();

        self::assertTrue($policy->canManageCompany(44, [10, 44, 91]));
        self::assertFalse($policy->canManageCompany(45, [10, 44, 91]));
    }
}
