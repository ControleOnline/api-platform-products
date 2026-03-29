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
use ControleOnline\Repository\ProductInventoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'PUBLIC_ACCESS\')'),
        new Put(
            security: 'is_granted(\'ROLE_CLIENT\')',
            denormalizationContext: ['groups' => ['product_inventory:write']]
        ),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(
            securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')',
            denormalizationContext: ['groups' => ['product_inventory:write']]
        ),
        new GetCollection(security: 'is_granted(\'PUBLIC_ACCESS\')'),
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product_inventory:read']],
    denormalizationContext: ['groups' => ['product_inventory:write']]
)]
#[ApiFilter(OrderFilter::class, properties: ['id', 'available', 'sales', 'ordered', 'transit'])]
#[ApiFilter(SearchFilter::class, properties: ['id' => 'exact', 'inventory' => 'exact', 'product' => 'exact'])]
#[ORM\Table(name: 'product_inventory')]
#[ORM\Index(name: 'inventory_id', columns: ['inventory_id'])]
#[ORM\Index(name: 'product_id', columns: ['product_id'])]
#[ORM\Entity(repositoryClass: ProductInventoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProductInventory
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    #[Groups(['product_inventory:read'])]
    private $id;

    #[ORM\ManyToOne(targetEntity: Inventory::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['product_inventory:read', 'product_inventory:write'])]
    private $inventory;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['product_inventory:read', 'product_inventory:write'])]
    private $product;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['product_inventory:read'])]
    private int $available = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['product_inventory:read'])]
    private int $sales = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['product_inventory:read'])]
    private int $ordered = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['product_inventory:read'])]
    private int $transit = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['product_inventory:read', 'product_inventory:write'])]
    private int $minimum = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['product_inventory:read', 'product_inventory:write'])]
    private int $maximum = 0;

    public function getId(): ?int
    {
        return $this->id;
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
    public function getSales(): int
    {
        return $this->sales;
    }
    public function getOrdered(): int
    {
        return $this->ordered;
    }
    public function getTransit(): int
    {
        return $this->transit;
    }

    public function setAvailable(int $v): self
    {
        throw new \RuntimeException('available não pode ser alterado manualmente.');
    }
    public function setSales(int $v): self
    {
        throw new \RuntimeException('sales não pode ser alterado manualmente.');
    }
    public function setOrdered(int $v): self
    {
        throw new \RuntimeException('ordered não pode ser alterado manualmente.');
    }
    public function setTransit(int $v): self
    {
        throw new \RuntimeException('transit não pode ser alterado manualmente.');
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