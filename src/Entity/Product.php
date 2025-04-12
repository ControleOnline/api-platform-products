<?php

namespace ControleOnline\Entity; 
use ControleOnline\Listener\LogListener;

use Doctrine\ORM\Mapping as ORM;
use ControleOnline\Entity\People;
use ControleOnline\Entity\ProductUnity;
use ControleOnline\Entity\Inventory;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\Common\Collections\Collection;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ControleOnline\Filter\RandomOrderFilter;

/**
 * Product
 */
#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'PUBLIC_ACCESS\')'),
        new Put(security: 'is_granted(\'ROLE_CLIENT\')', denormalizationContext: ['groups' => ['product:write']]),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'PUBLIC_ACCESS\')'),
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']]
)]
#[ApiFilter(OrderFilter::class, properties: ['product', 'price', 'description'])]
#[ApiFilter(RandomOrderFilter::class)]
#[ORM\Table(name: 'product')]
#[ORM\Index(name: 'product_unity_id', columns: ['product_unity_id'])]
#[ORM\Index(name: 'IDX_D34A04AD979B1AD6', columns: ['company_id'])]
#[ORM\Index(name: 'default_out_inventory_id', columns: ['default_out_inventory_id'])]
#[ORM\Index(name: 'default_in_inventory_id', columns: ['default_in_inventory_id'])]
#[ORM\UniqueConstraint(name: 'company_id', columns: ['company_id', 'sku'])]
#[ORM\Entity(repositoryClass: \ControleOnline\Repository\ProductRepository::class)]
class Product
{
    /**
     * @var int
     * @Groups({"product_category:read","product:read","order_product:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    /**
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['product' => 'partial'])]
    #[ORM\Column(name: 'product', type: 'string', length: 255, nullable: true)]
    private $product;

    /**
     * @Groups({"product:read","product_category:read","order_product:read"})
     */
    #[ApiFilter(filterClass: ExistsFilter::class, properties: ['productFiles'])]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productFiles.file.fileType' => 'exact'])]
    #[ORM\OneToMany(targetEntity: \ProductFile::class, mappedBy: 'product')]
    private $productFiles;

    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productCategory.category' => 'exact'])]
    #[ORM\OneToMany(targetEntity: \ProductCategory::class, mappedBy: 'product')]
    private $productCategory;

    /**
     * @var string|null
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['sku' => 'partial'])]
    #[ORM\Column(name: 'sku', type: 'string', length: 32, nullable: true, options: ['default' => 'NULL'])]
    private $sku = null;

    /**
     * @var string
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['type' => 'exact'])]
    #[ORM\Column(name: 'type', type: 'string', length: 0, nullable: false, options: ['default' => "'product'"])]
    private $type = 'product';

    /**
     * @var float
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ORM\Column(name: 'price', type: 'float', precision: 10, scale: 0, nullable: false)]
    private $price = 0;

    /**
     * @var string
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productCondition' => 'exact'])]
    #[ORM\Column(name: 'product_condition', type: 'string', length: 0, nullable: false, options: ['default' => "'new'"])]
    private $productCondition = 'new';

    /**
     * @var string
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ORM\Column(name: 'description', type: 'string', length: 0, nullable: false)]
    private $description = '';

    /**
     * @var bool
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['featured' => 'exact'])]
    #[ORM\Column(name: 'featured', type: 'boolean', nullable: false, options: ['default' => '0'])]
    private $featured = false;

    /**
     * @var bool
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['active' => 'exact'])]
    #[ORM\Column(name: 'active', type: 'boolean', nullable: false, options: ['default' => '1'])]
    private $active = true;

    /**
     * @var \ControleOnline\Entity\People
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['company' => 'exact'])]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: People::class)]
    private $company;

    /**
     * @var ProductUnity
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ORM\JoinColumn(name: 'product_unity_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: \ProductUnity::class)]
    private $productUnit;

    /**
     * @var Queue
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ORM\JoinColumn(name: 'queue_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: \Queue::class)]
    private $queue;

    /**
     * @var \ControleOnline\Entity\Inventory
     * @Groups({"product:read", "product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['defaultOutInventory' => 'exact'])]
    #[ORM\JoinColumn(name: 'default_out_inventory_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: Inventory::class)]
    private $defaultOutInventory;

    /**
     * @var \ControleOnline\Entity\Inventory
     * @Groups({"product:read", "product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['defaultInInventory' => 'exact'])]
    #[ORM\JoinColumn(name: 'default_in_inventory_id', referencedColumnName: 'id', nullable: true)]
    #[ORM\ManyToOne(targetEntity: Inventory::class)]
    private $defaultInInventory;

    public function __construct()
    {
        $this->productFiles = new \Doctrine\Common\Collections\ArrayCollection();
        $this->productCategory = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getProduct(): string
    {
        return $this->product;
    }

    public function setProduct(string $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): self
    {
        $this->sku = $sku;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
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

    public function getProductCondition(): string
    {
        return $this->productCondition;
    }

    public function setProductCondition(string $productCondition): self
    {
        $this->productCondition = $productCondition;
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

    public function getCompany(): ?People
    {
        return $this->company;
    }

    public function setCompany(People $company): self
    {
        $this->company = $company;
        return $this;
    }

    public function getProductUnit(): ProductUnity
    {
        return $this->productUnit;
    }

    public function setProductUnit(ProductUnity $productUnit): self
    {
        $this->productUnit = $productUnit;
        return $this;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getQueue()
    {
        return $this->queue;
    }

    public function setQueue($queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    public function getProductFiles(): Collection
    {
        return $this->productFiles;
    }

    public function getProductCategory(): Collection
    {
        return $this->productCategory;
    }

    public function getFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): self
    {
        $this->featured = $featured;
        return $this;
    }

    public function getDefaultOutInventory(): ?Inventory
    {
        return $this->defaultOutInventory;
    }

    public function setDefaultOutInventory(Inventory $defaultOutInventory): self
    {
        $this->defaultOutInventory = $defaultOutInventory;
        return $this;
    }

    public function getDefaultInInventory(): ?Inventory
    {
        return $this->defaultInInventory;
    }

    public function setDefaultInInventory(Inventory $defaultInInventory): self
    {
        $this->defaultInInventory = $defaultInInventory;
        return $this;
    }
}
