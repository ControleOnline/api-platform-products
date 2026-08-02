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
use ControleOnline\Repository\ProductShowcaseItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'ROLE_HUMAN\')'),
        new GetCollection(security: 'is_granted(\'ROLE_HUMAN\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_HUMAN\')'),
        new Put(security: 'is_granted(\'ROLE_HUMAN\')'),
        new Delete(security: 'is_granted(\'ROLE_HUMAN\')'),
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product_showcase_item:read']],
    denormalizationContext: ['groups' => ['product_showcase_item:write']]
)]
#[ApiFilter(OrderFilter::class, properties: [
    'sortOrder' => ['nulls_comparison' => 'nulls_always_last'],
    'product.product',
    'showcase.name',
    'id',
    'price',
    'active',
    'published',
    'updatedAt',
])]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'showcase' => 'exact',
    'showcase.company' => 'exact',
    'showcase.integrationKey' => 'exact',
    'product' => 'exact',
    'outInventory' => 'exact',
    'active' => 'exact',
    'published' => 'exact',
    'externalCode' => 'partial',
])]
#[ORM\Table(name: 'product_showcase_item')]
#[ORM\UniqueConstraint(name: 'product_showcase_item_showcase_product', columns: ['showcase_id', 'product_id'])]
#[ORM\Index(name: 'product_showcase_item_product_active', columns: ['product_id', 'active'])]
#[ORM\Index(name: 'product_showcase_item_inventory', columns: ['out_inventory_id'])]
#[ORM\Entity(repositoryClass: ProductShowcaseItemRepository::class)]
class ProductShowcaseItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    #[Groups(['product_showcase_item:read', 'order_product:read', 'order_details:read'])]
    private int $id = 0;

    #[ORM\ManyToOne(targetEntity: ProductShowcase::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'showcase_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['product_showcase_item:read', 'product_showcase_item:write', 'order_product:read', 'order_details:read'])]
    private ProductShowcase $showcase;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['product_showcase_item:read', 'product_showcase_item:write'])]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: Inventory::class)]
    #[ORM\JoinColumn(name: 'out_inventory_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['product_showcase_item:read', 'product_showcase_item:write', 'order_product:read', 'order_details:read'])]
    private ?Inventory $outInventory = null;

    #[ORM\Column(name: 'external_code', type: 'string', length: 160, nullable: true)]
    #[Groups(['product_showcase_item:read', 'product_showcase_item:write'])]
    private ?string $externalCode = null;

    #[ORM\Column(name: 'price', type: 'decimal', precision: 12, scale: 2, nullable: false)]
    #[Groups(['product_showcase_item:read', 'product_showcase_item:write'])]
    private string $price = '0.00';

    #[ORM\Column(name: 'active', type: 'boolean', nullable: false, options: ['default' => 1])]
    #[Groups(['product_showcase_item:read', 'product_showcase_item:write'])]
    private bool $active = true;

    #[ORM\Column(name: 'published', type: 'boolean', nullable: false, options: ['default' => 0])]
    #[Groups(['product_showcase_item:read', 'product_showcase_item:write'])]
    private bool $published = false;

    #[ORM\Column(name: 'sort_order', type: 'integer', nullable: true)]
    #[Groups(['product_showcase_item:read', 'product_showcase_item:write'])]
    private ?int $sortOrder = null;

    #[ORM\Column(name: 'sync_hash', type: 'string', length: 128, nullable: true)]
    #[Groups(['product_showcase_item:read', 'product_showcase_item:write'])]
    private ?string $syncHash = null;

    #[ORM\Column(name: 'sync_synced_at', type: 'datetime', nullable: true)]
    #[Groups(['product_showcase_item:read', 'product_showcase_item:write'])]
    private ?\DateTimeInterface $syncSyncedAt = null;

    #[ORM\Column(name: 'settings', type: 'json', nullable: true)]
    #[Groups(['product_showcase_item:read', 'product_showcase_item:write'])]
    private ?array $settings = [];

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false, insertable: false, updatable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP')]
    #[Groups(['product_showcase_item:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: false, insertable: false, updatable: false, columnDefinition: 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')]
    #[Groups(['product_showcase_item:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getShowcase(): ProductShowcase
    {
        return $this->showcase;
    }

    public function setShowcase(ProductShowcase $showcase): self
    {
        $this->showcase = $showcase;
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

    public function getOutInventory(): ?Inventory
    {
        return $this->outInventory;
    }

    public function setOutInventory(?Inventory $outInventory): self
    {
        $this->outInventory = $outInventory;
        return $this;
    }

    public function getExternalCode(): ?string
    {
        return $this->externalCode;
    }

    public function setExternalCode(?string $externalCode): self
    {
        $value = trim((string) $externalCode);
        $this->externalCode = $value !== '' ? $value : null;
        return $this;
    }

    public function getPrice(): float
    {
        return (float) $this->price;
    }

    public function setPrice(float|string $price): self
    {
        $this->price = number_format((float) $price, 2, '.', '');
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

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): self
    {
        $this->published = $published;
        return $this;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(?int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function getSyncHash(): ?string
    {
        return $this->syncHash;
    }

    public function setSyncHash(?string $syncHash): self
    {
        $value = trim((string) $syncHash);
        $this->syncHash = $value !== '' ? $value : null;
        return $this;
    }

    public function getSyncSyncedAt(): ?\DateTimeInterface
    {
        return $this->syncSyncedAt;
    }

    public function setSyncSyncedAt(null|\DateTimeInterface|string $syncSyncedAt): self
    {
        if (is_string($syncSyncedAt)) {
            $value = trim($syncSyncedAt);
            $this->syncSyncedAt = $value !== '' ? new \DateTime($value) : null;
            return $this;
        }

        if ($syncSyncedAt instanceof \DateTimeImmutable) {
            $this->syncSyncedAt = \DateTime::createFromImmutable($syncSyncedAt);
            return $this;
        }

        $this->syncSyncedAt = $syncSyncedAt;
        return $this;
    }

    public function getSettings(): array
    {
        return $this->settings ?? [];
    }

    public function setSettings(?array $settings): self
    {
        $this->settings = $settings ?? [];
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
