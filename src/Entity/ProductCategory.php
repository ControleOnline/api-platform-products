<?php

namespace ControleOnline\Entity;

use Symfony\Component\Serializer\Attribute\Groups;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ControleOnline\Repository\ProductCategoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')'),
        new Put(
            security: 'is_granted(\'ROLE_CLIENT\')',
            denormalizationContext: ['groups' => ['product_category:write']]
        ),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')')
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product_category:read']],
    denormalizationContext: ['groups' => ['product_category:write']]
)]
#[ApiFilter(OrderFilter::class, properties: ['product.product'])]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['category' => 'exact', 'category.company' => 'exact', 'category.context' => 'exact', 'product' => 'exact'])]
#[ORM\Table(name: 'product_category')]
#[ORM\Index(name: 'category_id', columns: ['category_id'])]
#[ORM\Index(name: 'IDX_CDFC73564584665A', columns: ['product_id'])]
#[ORM\UniqueConstraint(name: 'product_id', columns: ['product_id', 'category_id'])]
#[ORM\Entity(repositoryClass: ProductCategoryRepository::class)]
class ProductCategory
{
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['product_category:read'])]
    private int $id;

    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[Groups(['product_category:read', 'product_category:write'])]
    private Category $category;

    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[Groups(['product_category:read', 'product_category:write'])]
    private Product $product;

    public function getId(): int
    {
        return $this->id;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }
}