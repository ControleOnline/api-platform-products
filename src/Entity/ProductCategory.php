<?php

namespace ControleOnline\Entity; 
use ControleOnline\Listener\LogListener;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;

/**
 * ProductCategory
 */
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
#[ORM\Table(name: 'product_category')]
#[ORM\Index(name: 'category_id', columns: ['category_id'])]
#[ORM\Index(name: 'IDX_CDFC73564584665A', columns: ['product_id'])]
#[ORM\UniqueConstraint(name: 'product_id', columns: ['product_id', 'category_id'])]
#[ORM\Entity(repositoryClass: \ControleOnline\Repository\ProductCategoryRepository::class)]

class ProductCategory
{
    /**
     * @var int
     *
     * @Groups({"product_category:read"})
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    /**
     * @var ControleOnline\Entity\Category
     *
     * @Groups({"product_category:read","product_category:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['category' => 'exact'])]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['category.company' => 'exact'])]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['category.context' => 'exact'])]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: \ControleOnline\Entity\Category::class)]

    private $category;

    /**
     * @var Product
     *
     * @Groups({"product_category:read","product_category:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['product' => 'exact'])]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: \Product::class)]

    private $product;

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the value of category
     */
    public function getCategory(): Category
    {
        return $this->category;
    }

    /**
     * Set the value of category
     */
    public function setCategory(Category $category): self
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get the value of product
     */
    public function getProduct(): Product
    {
        return $this->product;
    }

    /**
     * Set the value of product
     */
    public function setProduct(Product $product): self
    {
        $this->product = $product;

        return $this;
    }
}
