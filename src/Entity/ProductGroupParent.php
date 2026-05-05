<?php

namespace ControleOnline\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ControleOnline\Repository\ProductGroupParentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'PUBLIC_ACCESS\')'),
        new Put(
            security: 'is_granted(\'ROLE_HUMAN\')',
            denormalizationContext: ['groups' => ['product_group_parent:write']]
        ),
        new Delete(security: 'is_granted(\'ROLE_HUMAN\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_HUMAN\')'),
        new GetCollection(security: 'is_granted(\'PUBLIC_ACCESS\')')
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product_group_parent:read']],
    denormalizationContext: ['groups' => ['product_group_parent:write']]
)]
#[ApiFilter(OrderFilter::class, properties: ['productGroup.productGroup' => 'ASC'])]
#[ORM\Table(name: 'product_group_parent')]
#[ORM\UniqueConstraint(name: 'product_group_parent_unique', columns: ['product_group_id', 'parent_product_id'])]
#[ORM\Index(name: 'product_group_parent_product_id', columns: ['parent_product_id'])]
#[ORM\Entity(repositoryClass: ProductGroupParentRepository::class)]
class ProductGroupParent
{
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['product_group_parent:read', 'product_group_parent:write'])]
    private $id;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productGroup' => 'exact', 'productGroup.productGroup' => 'partial'])]
    #[ORM\JoinColumn(name: 'product_group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: ProductGroup::class, inversedBy: 'parentProducts')]
    #[Groups(['product_group_parent:read', 'product_group_parent:write'])]
    private $productGroup;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['parentProduct' => 'exact', 'parentProduct.company' => 'exact'])]
    #[ORM\JoinColumn(name: 'parent_product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[Groups(['product_group_parent:read', 'product_group_parent:write'])]
    private $parentProduct;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['active' => 'exact'])]
    #[ORM\Column(name: 'active', type: 'boolean', nullable: false, options: ['default' => '1'])]
    #[Groups(['product_group_parent:read', 'product_group_parent:write'])]
    private $active = true;

    public function getId()
    {
        return $this->id;
    }

    public function getProductGroup(): ?ProductGroup
    {
        return $this->productGroup;
    }

    public function setProductGroup(?ProductGroup $productGroup): self
    {
        $this->productGroup = $productGroup;
        return $this;
    }

    public function getParentProduct(): ?Product
    {
        return $this->parentProduct;
    }

    public function setParentProduct(?Product $parentProduct): self
    {
        $this->parentProduct = $parentProduct;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

}
