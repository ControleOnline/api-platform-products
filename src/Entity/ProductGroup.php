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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\OneToMany;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;

/**
 * ProductGroup
 */
#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'PUBLIC_ACCESS\')',),
        new Put(
            security: 'is_granted(\'ROLE_CLIENT\')',
            denormalizationContext: ['groups' => ['product_group:write']]
        ),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'PUBLIC_ACCESS\')',)
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product_group:read']],
    denormalizationContext: ['groups' => ['product_group:write']]
)]
#[ApiFilter(OrderFilter::class, properties: ['productGroup'])]
#[ORM\Table(name: 'product_group')]
#[ORM\Entity(repositoryClass: \ControleOnline\Repository\ProductGroupRepository::class)]

class ProductGroup
{
    /**
     * @var int
     *
     * @Groups({"product_group:read","product_group:write","order_product:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    /**
     * @var string
     *
     * @Groups({"product_group:read","product_group:write","order_product:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productGroup' => 'exact'])]
    #[ORM\Column(name: 'product_group', type: 'string', length: 255, nullable: false)]

    private $productGroup;

    /**
     * @var string
     *
     * @Groups({"product_group:read","product_group:write","order_product:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['priceCalculation' => 'exact'])]
    #[ORM\Column(name: 'price_calculation', type: 'string', length: 0, nullable: false, options: ['default' => "'sum'"])]

    private $priceCalculation = 'sum';

    /**
     * @var bool
     *
     * @Groups({"product_group:read","product_group:write","order_product:read"})
     */
    #[ORM\Column(name: 'required', type: 'boolean', nullable: false)]
    private $required = 0;

    /**
     * @var int|null
     *
     * @Groups({"product_group:read","product_group:write","order_product:read"})
     */
    #[ORM\Column(name: 'minimum', type: 'integer', nullable: true, options: ['default' => 'NULL'])]
    private $minimum = NULL;

    /**
     * @var int|null
     *
     * @Groups({"product_group:read","product_group:write","order_product:read"})
     */
    #[ORM\Column(name: 'maximum', type: 'integer', nullable: true, options: ['default' => 'NULL'])]
    private $maximum = NULL;

    /**
     * @var bool
     *
     * @Groups({"product_group:read","product_group:write","order_product:read"})
     */
    #[ORM\Column(name: 'active', type: 'boolean', nullable: false, options: ['default' => '1'])]
    private $active = true;

    /**
     * @var int
     *
     * @Groups({"product_group:read","product_group:write","order_product:read"})
     */
    #[ORM\Column(name: 'group_order', type: 'integer', nullable: false)]
    private $groupOrder = 0;

    /**
     * @var Collection|ProductGroupProduct[]
     *
     * @Groups({"product_group:write"})
     */
    #[ORM\OneToMany(targetEntity: ProductGroupProduct::class, mappedBy: 'productGroup', orphanRemoval: true)]
    private $products;

    /**
     * @Groups({"product_group:read","product_group:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productParent' => 'exact'])]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\ManyToOne(targetEntity: \ControleOnline\Entity\Product::class)]

    private $productParent;

    public function __construct()
    {
        $this->products = new ArrayCollection();
    }

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the value of productGroup
     */
    public function getProductGroup(): string
    {
        return $this->productGroup;
    }

    /**
     * Set the value of productGroup
     */
    public function setProductGroup(string $productGroup): self
    {
        $this->productGroup = $productGroup;

        return $this;
    }

    /**
     * Get the value of priceCalculation
     */
    public function getPriceCalculation(): string
    {
        return $this->priceCalculation;
    }

    /**
     * Set the value of priceCalculation
     */
    public function setPriceCalculation(string $priceCalculation): self
    {
        $this->priceCalculation = $priceCalculation;

        return $this;
    }

    /**
     * Get the value of required
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Set the value of required
     */
    public function setRequired(bool $required): self
    {
        $this->required = $required;

        return $this;
    }

    /**
     * Get the value of minimum
     */
    public function getMinimum(): ?int
    {
        return (float) $this->minimum;
    }

    /**
     * Set the value of minimum
     */
    public function setMinimum(?int $minimum): self
    {
        $this->minimum = $minimum;

        return $this;
    }

    /**
     * Get the value of maximum
     */
    public function getMaximum(): ?int
    {
        return (float) $this->maximum;
    }

    /**
     * Set the value of maximum
     */
    public function setMaximum(?int $maximum): self
    {
        $this->maximum = $maximum;

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

    /**
     * Get the value of groupOrder
     */
    public function getGroupOrder()
    {
        return $this->groupOrder;
    }

    /**
     * Set the value of groupOrder
     */
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

    /**
     * @return Collection|ProductGroupProduct[]
     */
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
            // set the owning side to null (unless already changed)
            if ($product->getProductGroup() === $this) {
                $product->setProductGroup(null);
            }
        }

        return $this;
    }

    /**
     * Get the value of productParent
     */
    public function getProductParent()
    {
        return $this->productParent;
    }

    /**
     * Set the value of productParent
     */
    public function setProductParent($productParent): self
    {
        $this->productParent = $productParent;

        return $this;
    }
}
