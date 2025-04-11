<?php

namespace ControleOnline\Entity; 
use ControleOnline\Listener\LogListener;

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
#[ORM\Table(name: 'product_inventory')]
#[ORM\Index(name: 'inventory_id', columns: ['inventory_id'])]
#[ORM\Index(name: 'product_id', columns: ['product_id'])]
#[ORM\Entity(repositoryClass: \ControleOnline\Repository\ProductInventoryRepository::class)]
class ProductInventory
{
    /**
     * @var int
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    /**
     * @var \ControleOnline\Entity\Inventory
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    #[ORM\JoinColumn(name: 'inventory_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: Inventory::class)]
    private $inventory;

    /**
     * @var \ControleOnline\Entity\Product
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: Product::class)]
    private $product;



    /**
     * @var int
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    #[ORM\Column(name: 'available', type: 'integer', nullable: false, options: ['default' => 0])]
    private $available = 0;

    /**
     * @var int
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    #[ORM\Column(name: 'sales', type: 'integer', nullable: false, options: ['default' => 0])]
    private $sales = 0;

    /**
     * @var int
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    #[ORM\Column(name: 'ordered', type: 'integer', nullable: false, options: ['default' => 0])]
    private $ordered = 0;

    /**
     * @var int
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    #[ORM\Column(name: 'transit', type: 'integer', nullable: false, options: ['default' => 0])]
    private $transit = 0;

    /**
     * @var int
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    #[ORM\Column(name: 'minimum', type: 'integer', nullable: false, options: ['default' => 0])]
    private $minimum = 0;

    /**
     * @var int
     * @Groups({"product_inventory:read", "product_inventory:write"})
     */
    #[ORM\Column(name: 'maximum', type: 'integer', nullable: false, options: ['default' => 0])]
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
