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
use ControleOnline\Listener\LogListener;
use ControleOnline\Repository\ProductGroupProductRepository;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'PUBLIC_ACCESS\')'),
        new Put(
            security: 'is_granted(\'ROLE_CLIENT\')',
            denormalizationContext: ['groups' => ['product_group_product:write']]
        ),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'PUBLIC_ACCESS\')')
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product_group_product:read']],
    denormalizationContext: ['groups' => ['product_group_product:write']]
)]
#[ApiFilter(OrderFilter::class, properties: ['productGroup.productGroup' => 'ASC', 'product.product' => 'ASC'])]
#[ORM\Table(name: 'product_group_product')]
#[ORM\Entity(repositoryClass: ProductGroupProductRepository::class)]
class ProductGroupProduct
{
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['product_group_product:read', 'product_group:write', 'product_group_product:write'])]
    private $id;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['product' => 'exact'])]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[Groups(['product_group_product:read', 'product_group:write', 'product_group_product:write'])]
    private $product;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productGroup' => 'exact'])]
    #[ORM\JoinColumn(name: 'product_group_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: ProductGroup::class)]
    #[Groups(['product_group_product:read', 'product_group:write', 'product_group_product:write'])]
    private $productGroup;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productType' => 'exact'])]
    #[ORM\Column(name: 'product_type', type: 'string', columnDefinition: "ENUM('feedstock', 'component', 'package')", nullable: false)]
    #[Groups(['product_group_product:read', 'product_group:write', 'product_group_product:write'])]
    private $productType = 'component';

    #[ORM\JoinColumn(name: 'product_child_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[Groups(['product_group_product:read', 'product_group:write', 'product_group_product:write'])]
    private $productChild;

    #[ORM\Column(name: 'quantity', type: 'float', precision: 10, scale: 2, nullable: false, options: ['default' => '1.00'])]
    #[Groups(['product_group_product:read', 'product_group:write', 'product_group_product:write'])]
    private $quantity = 0;

    #[ORM\Column(name: 'price', type: 'float', precision: 10, scale: 2, nullable: false)]
    #[Groups(['product_group_product:read', 'product_group:write', 'product_group_product:write'])]
    private $price = 0;

    #[ORM\Column(name: 'active', type: 'boolean', nullable: false, options: ['default' => '1'])]
    #[Groups(['product_group_product:read', 'product_group:write', 'product_group_product:write'])]
    private $active = true;

    public function getId()
    {
        return $this->id;
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

    public function getProductGroup(): ?ProductGroup
    {
        return $this->productGroup;
    }

    public function setProductGroup(?ProductGroup $productGroup): self
    {
        $this->productGroup = $productGroup;
        return $this;
    }

    public function getProductChild(): ?Product
    {
        return $this->productChild;
    }

    public function setProductChild(?Product $productChild): self
    {
        $this->productChild = $productChild;
        return $this;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getProductType(): string
    {
        return $this->productType;
    }

    public function setProductType(string $productType): self
    {
        $this->productType = $productType;
        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;
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