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
use ControleOnline\Repository\ProductShowcaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    normalizationContext: ['groups' => ['product_showcase:read']],
    denormalizationContext: ['groups' => ['product_showcase:write']]
)]
#[ApiFilter(OrderFilter::class, properties: ['id', 'name', 'integrationKey', 'active'])]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'company' => 'exact',
    'integrationKey' => 'exact',
    'active' => 'exact',
    'name' => 'partial',
])]
#[ORM\Table(name: 'product_showcase')]
#[ORM\UniqueConstraint(name: 'product_showcase_company_integration_name', columns: ['company_id', 'integration_key', 'name'])]
#[ORM\Index(name: 'product_showcase_company_integration_active', columns: ['company_id', 'integration_key', 'active'])]
#[ORM\Entity(repositoryClass: ProductShowcaseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProductShowcase
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    #[Groups(['product_showcase:read', 'product_showcase_item:read', 'order_product:read'])]
    private int $id = 0;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['product_showcase:read', 'product_showcase:write', 'product_showcase_item:read'])]
    private People $company;

    #[ORM\Column(name: 'name', type: 'string', length: 120, nullable: false)]
    #[Groups(['product_showcase:read', 'product_showcase:write', 'product_showcase_item:read'])]
    private string $name = '';

    #[ORM\Column(name: 'integration_key', type: 'string', length: 50, nullable: false)]
    #[Groups(['product_showcase:read', 'product_showcase:write', 'product_showcase_item:read'])]
    private string $integrationKey = '';

    #[ORM\Column(name: 'external_store_code', type: 'string', length: 120, nullable: true)]
    #[Groups(['product_showcase:read', 'product_showcase:write'])]
    private ?string $externalStoreCode = null;

    #[ORM\Column(name: 'settings', type: 'json', nullable: true)]
    #[Groups(['product_showcase:read', 'product_showcase:write'])]
    private ?array $settings = [];

    #[ORM\Column(name: 'active', type: 'boolean', nullable: false, options: ['default' => 1])]
    #[Groups(['product_showcase:read', 'product_showcase:write'])]
    private bool $active = true;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false)]
    #[Groups(['product_showcase:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: false)]
    #[Groups(['product_showcase:read'])]
    private \DateTimeInterface $updatedAt;

    #[ORM\OneToMany(targetEntity: ProductShowcaseItem::class, mappedBy: 'showcase')]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->integrationKey = self::normalizeIntegrationKey($this->integrationKey);
        $this->updatedAt = new \DateTimeImmutable();
        if (!isset($this->createdAt)) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public static function normalizeIntegrationKey(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));
        $normalized = str_replace(['_', ' '], ['-', '-'], $normalized);

        return match ($normalized) {
            '99', '99-food', 'food99' => '99food',
            'i-food' => 'ifood',
            'pdv' => 'pos',
            default => $normalized,
        };
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCompany(): People
    {
        return $this->company;
    }

    public function setCompany(People $company): self
    {
        $this->company = $company;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);
        return $this;
    }

    public function getIntegrationKey(): string
    {
        return $this->integrationKey;
    }

    public function setIntegrationKey(string $integrationKey): self
    {
        $this->integrationKey = self::normalizeIntegrationKey($integrationKey);
        return $this;
    }

    public function getExternalStoreCode(): ?string
    {
        return $this->externalStoreCode;
    }

    public function setExternalStoreCode(?string $externalStoreCode): self
    {
        $value = trim((string) $externalStoreCode);
        $this->externalStoreCode = $value !== '' ? $value : null;
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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }
}
