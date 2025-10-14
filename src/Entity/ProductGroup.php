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
use ControleOnline\Repository\ProductGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'PUBLIC_ACCESS\')'),
        new Put(
            security: 'is_granted(\'ROLE_CLIENT\')',
            denormalizationContext: ['groups' => ['product_group:write']]
        ),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'PUBLIC_ACCESS\')')
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product_group:read']],
    denormalizationContext: ['groups' => ['product_group:write']]
)]
#[ApiFilter(OrderFilter::class, properties: ['productGroup'])]
#[ORM\Table(name: 'product_group')]
#[ORM\Entity(repositoryClass: ProductGroupRepository::class)]
class ProductGroup
{
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['product_group:read', 'product_group:write', 'order_product:read'])]
    private $id;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productGroup' => 'exact'])]
    #[ORM\Column(name: 'product_group', type: 'string', length: 255, nullable: false)]
    #[Groups(['product_group:read', 'product_group:write', 'order_product:read'])]
    private $productGroup;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['priceCalculation' => 'exact'])]
    #[ORM\Column(name: 'price_calculation', type: 'string', length: 0, nullable: false, options: ['default' => "'sum'"])]
    #[Groups(['product_group:read', 'product_group:write', 'order_product:read'])]
    private $priceCalculation = 'sum';

    #[ORM\Column(name: 'required', type: 'boolean', nullable: false)]
    #[Groups(['product_group:read', 'product_group:write', 'order_product:read'])]
    private $required = false;

    #[ORM\Column(name: 'minimum', type: 'integer', nullable: true, options: ['default' => 'NULL'])]
    #[Groups(['product_group:read', 'product_group:write', 'order_product:read'])]
    private $minimum = null;

    #[ORM\Column(name: 'maximum', type: 'integer', nullable: true, options: ['default' => 'NULL'])]
    #[Groups(['product_group:read', 'product_group:write', 'order_product:read'])]
    private $maximum = null;

    #[ORM\Column(name: 'active', type: 'boolean', nullable: false, options: ['default' => '1'])]
    #[Groups(['product_group:read', 'product_group:write', 'order_product:read'])]
    private $active = true;

    #[ORM\Column(name: 'group_order', type: 'integer', nullable: false)]
    #[Groups(['product_group:read', 'product_group:write', 'order_product:read'])]
    private $groupOrder = 0;

    #[ORM\OneToMany(targetEntity: ProductGroupProduct::class, mappedBy: 'productGroup', orphanRemoval: true)]
    #[Groups(['product_group:write'])]
    private $products;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['parentProduct' => 'exact'])]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[Groups(['product_group:read', 'product_group:write'])]
    private $parentProduct;

    public function __construct()
    {
        $this->products = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getProductGroup(): string
    {
        return $this->productGroup;
    }

    public function setProductGroup(string $productGroup): self
    {
        $this->productGroup = $productGroup;
        return $this;
    }

    public function getPriceCalculation(): string
    {
        return $this->priceCalculation;
    }

    public function setPriceCalculation(string $priceCalculation): self
    {
        $this->priceCalculation = $priceCalculation;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): self
    {
        $this->required = $required;
        return $this;
    }

    public function getMinimum(): ?int
    {
        return $this->minimum;
    }

    public function setMinimum(?int $minimum): self
    {
        $this->minimum = $minimum;
        return $this;
    }

    public function getMaximum(): ?int
    {
        return $this->maximum;
    }

    public function setMaximum(?int $maximum): self
    {
        $this->maximum = $maximum;
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

    public function getGroupOrder()
    {
        return $this->groupOrder;
    }

    public function setGroupOrder($groupOrder): self
    {
        $this->groupOrder = $groupOrder;
        return $this;
    }

    public function getRequired(): ?bool
    {
        return $this->required;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(ProductGroupProduct $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products[] = $product;
            $product->setProductGroup($this);
        }
        return $this;
    }

    public function removeProduct(ProductGroupProduct $product): self
    {
        if ($this->products->removeElement($product)) {
            if ($product->getProductGroup() === $this) {
                $product->setProductGroup(null);
            }
        }
        return $this;
    }

    public function getParentProduct()
    {
        return $this->parentProduct;
    }

    public function setParentProduct($parentProduct): self
    {
        $this->parentProduct = $parentProduct;
        return $this;
    }
}