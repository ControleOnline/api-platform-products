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
use ControleOnline\Repository\ProductPeopleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    operations: [
        new Get(uriTemplate: '/product_people/{id}', security: 'is_granted(\'PUBLIC_ACCESS\')'),
        new GetCollection(uriTemplate: '/product_people', security: 'is_granted(\'PUBLIC_ACCESS\')'),
        new Post(uriTemplate: '/product_people', security: 'is_granted(\'ROLE_CLIENT\')'),
        new Put(uriTemplate: '/product_people/{id}', security: 'is_granted(\'ROLE_CLIENT\')'),
        new Delete(uriTemplate: '/product_people/{id}', security: 'is_granted(\'ROLE_CLIENT\')'),
    ],
    normalizationContext: ['groups' => ['product_people:read']],
    denormalizationContext: ['groups' => ['product_people:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'product' => 'exact',
    'people' => 'exact',
    'role' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'costPrice',
    'leadTimeDays',
    'priority',
    'createdAt'
])]
#[ORM\Table(name: 'product_people')]
#[ORM\Index(name: 'IDX_PRODUCT_PEOPLE_PRODUCT', columns: ['product_id'])]
#[ORM\Index(name: 'IDX_PRODUCT_PEOPLE_PEOPLE', columns: ['people_id'])]
#[ORM\Entity(repositoryClass: ProductPeopleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProductPeople
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['product_people:read', 'product:read', 'people:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'productPeople')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['product_people:read', 'product_people:write'])]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: People::class, inversedBy: 'productPeople')]
    #[ORM\JoinColumn(name: 'people_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['product_people:read', 'product_people:write'])]
    private ?People $people = null;

    #[ORM\Column(type: 'string', columnDefinition: "ENUM('supplier','manufacturer','distributor')", options: ['default' => 'supplier'])]
    #[Groups(['product_people:read', 'product_people:write', 'product:read', 'people:read'])]
    private string $role = 'supplier';

    #[ORM\Column(name: 'cost_price', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Groups(['product_people:read', 'product_people:write', 'product:read', 'people:read'])]
    private ?string $costPrice = null;

    #[ORM\Column(name: 'lead_time_days', type: 'integer', nullable: true)]
    #[Groups(['product_people:read', 'product_people:write', 'product:read', 'people:read'])]
    private ?int $leadTimeDays = null;

    #[ORM\Column(name: 'supplier_sku', type: 'string', length: 100, nullable: true)]
    #[Groups(['product_people:read', 'product_people:write', 'product:read', 'people:read'])]
    private ?string $supplierSku = null;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    #[Groups(['product_people:read', 'product_people:write', 'product:read', 'people:read'])]
    private int $priority = 1;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: true)]
    #[Groups(['product_people:read', 'product:read', 'people:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    #[Groups(['product_people:read', 'product:read', 'people:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getPeople(): ?People
    {
        return $this->people;
    }

    public function setPeople(?People $people): self
    {
        $this->people = $people;
        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getCostPrice(): ?string
    {
        return $this->costPrice;
    }

    public function setCostPrice(?string $costPrice): self
    {
        $this->costPrice = $costPrice;
        return $this;
    }

    public function getLeadTimeDays(): ?int
    {
        return $this->leadTimeDays;
    }

    public function setLeadTimeDays(?int $leadTimeDays): self
    {
        $this->leadTimeDays = $leadTimeDays;
        return $this;
    }

    public function getSupplierSku(): ?string
    {
        return $this->supplierSku;
    }

    public function setSupplierSku(?string $supplierSku): self
    {
        $this->supplierSku = $supplierSku;
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[Groups(['product:read'])]
    #[SerializedName('people')]
    public function getPeopleSummary(): ?array
    {
        if (!$this->people instanceof People) {
            return null;
        }

        return [
            'id' => $this->people->getId(),
            'name' => $this->people->getName(),
            'alias' => $this->people->getAlias(),
            'peopleType' => $this->people->getPeopleType(),
        ];
    }

    #[Groups(['people:read'])]
    #[SerializedName('product')]
    public function getProductSummary(): ?array
    {
        if (!$this->product instanceof Product) {
            return null;
        }

        return [
            'id' => $this->product->getId(),
            'product' => $this->product->getProduct(),
            'sku' => $this->product->getSku(),
            'type' => $this->product->getType(),
            'price' => $this->product->getPrice(),
            'productCondition' => $this->product->getProductCondition(),
            'description' => $this->product->getDescription(),
            'active' => $this->product->isActive(),
        ];
    }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $now = new \DateTimeImmutable('now');

        if (!$this->createdAt instanceof \DateTimeInterface) {
            $this->createdAt = $now;
        }

        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now');
    }
}
