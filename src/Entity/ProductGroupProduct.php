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
 * ProductGroupProduct
 */
#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'PUBLIC_ACCESS\')',),
        new Put(
            security: 'is_granted(\'ROLE_CLIENT\')',
            denormalizationContext: ['groups' => ['product_group_product:write']]
        ),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'PUBLIC_ACCESS\')',)
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product_group_product:read']],
    denormalizationContext: ['groups' => ['product_group_product:write']]
)]

#[ApiFilter(OrderFilter::class, properties: ['productGroup.productGroup' => 'ASC', 'product.product' => 'ASC'])]
#[ORM\Table(name: 'product_group_product')]
#[ORM\Entity(repositoryClass: \ControleOnline\Repository\ProductGroupProductRepository::class)]

class ProductGroupProduct
{
    /**
     * @var int
     *
     * @Groups({"product_group_product:read","product_group:write","product_group_product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]

    private $id;

    /**
     * @var Product
     *
     * @Groups({"product_group_product:read","product_group:write","product_group_product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['product' => 'exact'])]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: \Product::class)]

    private $product;

    /**
     * @var ProductGroup
     *
     * @Groups({"product_group_product:read","product_group:write","product_group_product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productGroup' => 'exact'])]
    #[ORM\JoinColumn(name: 'product_group_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: \ProductGroup::class)]

    private $productGroup;

    /**
     * @var string
     *
     * @Groups({"product_group_product:read","product_group:write","product_group_product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productType' => 'exact'])]
    #[ORM\Column(name: 'product_type', type: 'string', columnDefinition: "ENUM('feedstock', 'component', 'package')", nullable: false)]

    private $productType;

    /**
     * @var Product
     *
     * @Groups({"product_group_product:read","product_group:write","product_group_product:write"})
     */
    #[ORM\JoinColumn(name: 'product_child_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: \Product::class)]
    private $productChild;

    /**
     * @var float
     *
     * @Groups({"product_group_product:read","product_group:write","product_group_product:write"})
     */
    #[ORM\Column(name: 'quantity', type: 'float', precision: 10, scale: 2, nullable: false, options: ['default' => '1.00'])]
    private $quantity = 0;

    /**
     * @var float
     *
     * @Groups({"product_group_product:read","product_group:write","product_group_product:write"})
     */
    #[ORM\Column(name: 'price', type: 'float', precision: 10, scale: 2, nullable: false)]
    private $price = 0;

    /**
     * @var bool
     *
     * @Groups({"product_group_product:read","product_group:write","product_group_product:write"})
     */
    #[ORM\Column(name: 'active', type: 'boolean', nullable: false, options: ['default' => '1'])]
    private $active = true;

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }



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

    /**
     * Get the value of productGroup
     */
    public function getProductGroup(): ?ProductGroup
    {
        return $this->productGroup;
    }

    /**
     * Set the value of productGroup
     */
    public function setProductGroup(?ProductGroup $productGroup): self
    {
        $this->productGroup = $productGroup;

        return $this;
    }

    /**
     * Get the value of productChild
     */
    public function getProductChild(): Product
    {
        return $this->productChild;
    }

    /**
     * Set the value of productChild
     */
    public function setProductChild(Product $productChild): self
    {
        $this->productChild = $productChild;

        return $this;
    }

    /**
     * Get the value of quantity
     */
    public function getQuantity(): float
    {
        return $this->quantity;
    }

    /**
     * Set the value of quantity
     */
    public function setQuantity(float $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Get the value of productType
     */
    public function getProductType(): string
    {
        return $this->productType;
    }

    /**
     * Set the value of productType
     */
    public function setProductType(string $productType): self
    {
        $this->productType = $productType;

        return $this;
    }

    /**
     * Get the value of price
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * Set the value of price
     */
    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Get the value of active
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Set the value of active
     */
    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }
}
