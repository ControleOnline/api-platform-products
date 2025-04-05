<?php

namespace ControleOnline\Entity;

use Doctrine\ORM\Mapping as ORM;
use ControleOnline\Entity\Inventory;
use ControleOnline\Entity\Product;
use ControleOnline\Entity\ProductUnity;
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
 * ProductInventory
 *
 * @ORM\Table(name="product_inventory", indexes={@ORM\Index(name="inventory_id", columns={"inventory_id"}), @ORM\Index(name="product_id", columns={"product_id"}), @ORM\Index(name="product_unity_id", columns={"product_unity_id"})})
 * @ORM\Entity(repositoryClass="ControleOnline\Repository\ProductInventoryRepository")
 */
#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'IS_AUTHENTICATED_ANONYMOUSLY\')'),
        new Put(security: 'is_granted(\'ROLE_CLIENT\')', denormalizationContext: ['groups' => ['product_inventory:write']]),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'IS_AUTHENTICATED_ANONYMOUSLY\')'),
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product_inventory:read']],
    denormalizationContext: ['groups' => ['product_inventory:write']]
)]
#[ApiFilter(OrderFilter::class, properties: ['id', 'available', 'sales', 'ordered', 'transit'])]
#[ApiFilter(SearchFilter::class, properties: ['id' => 'exact', 'inventory' => 'exact', 'product' => 'exact', 'productUnity' => 'exact'])]
class ProductInventory
{
    /**
     * @var int
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    private $id;

    /**
     * @var \ControleOnline\Entity\Inventory
     * @ORM\ManyToOne(targetEntity="\ControleOnline\Entity\Inventory")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="inventory_id", referencedColumnName="id", nullable=false)
     * })
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    private $inventory;

    /**
     * @var \ControleOnline\Entity\Product
     * @ORM\ManyToOne(targetEntity="\ControleOnline\Entity\Product")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="product_id", referencedColumnName="id", nullable=false)
     * })
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    private $product;

    /**
     * @var \ControleOnline\Entity\ProductUnity
     * @ORM\ManyToOne(targetEntity="\ControleOnline\Entity\ProductUnity")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="product_unity_id", referencedColumnName="id", nullable=false)
     * })
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    private $productUnity;

    /**
     * @var int
     * @ORM\Column(name="available", type="integer", nullable=false, options={"default"=0})
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    private $available = 0;

    /**
     * @var int
     * @ORM\Column(name="sales", type="integer", nullable=false, options={"default"=0})
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    private $sales = 0;

    /**
     * @var int
     * @ORM\Column(name="ordered", type="integer", nullable=false, options={"default"=0})
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    private $ordered = 0;

    /**
     * @var int
     * @ORM\Column(name="transit", type="integer", nullable=false, options={"default"=0})
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    private $transit = 0;

    /**
     * @var int
     * @ORM\Column(name="minimum", type="integer", nullable=false, options={"default"=0})
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    private $minimum = 0;

    /**
     * @var int
     * @ORM\Column(name="maximum", type="integer", nullable=false, options={"default"=0})
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    private $maximum = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getInventory(): ?Inventory
    {
        return $this->inventory;
    }

    public function setInventory(Inventory $inventory): self
    {
        $this->inventory = $inventory;
        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getProductUnity(): ?ProductUnity
    {
        return $this->productUnity;
    }

    public function setProductUnity(ProductUnity $productUnity): self
    {
        $this->productUnity = $productUnity;
        return $this;
    }

    public function getAvailable(): int
    {
        return $this->available;
    }

    public function setAvailable(int $available): self
    {
        $this->available = $available;
        return $this;
    }

    public function getSales(): int
    {
        return $this->sales;
    }

    public function setSales(int $sales): self
    {
        $this->sales = $sales;
        return $this;
    }

    public function getOrdered(): int
    {
        return $this->ordered;
    }

    public function setOrdered(int $ordered): self
    {
        $this->ordered = $ordered;
        return $this;
    }

    public function getTransit(): int
    {
        return $this->transit;
    }

    public function setTransit(int $transit): self
    {
        $this->transit = $transit;
        return $this;
    }

    public function getMinimum(): int
    {
        return $this->minimum;
    }

    public function setMinimum(int $minimum): self
    {
        $this->minimum = $minimum;
        return $this;
    }

    public function getMaximum(): int
    {
        return $this->maximum;
    }

    public function setMaximum(int $maximum): self
    {
        $this->maximum = $maximum;
        return $this;
    }
}