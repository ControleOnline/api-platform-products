<?php

namespace ControleOnline\Entity;

use Doctrine\ORM\Mapping as ORM;
use ControleOnline\Entity\People;
use ControleOnline\Entity\ProductUnity;
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
 *
 * @ORM\Table(name="product", uniqueConstraints={@ORM\UniqueConstraint(name="company_id", columns={"company_id", "sku"})}, indexes={@ORM\Index(name="product_unit_id", columns={"product_unit_id"}), @ORM\Index(name="IDX_D34A04AD979B1AD6", columns={"company_id"})})
 * @ORM\Entity(repositoryClass="ControleOnline\Repository\ProductRepository")
 */
#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'IS_AUTHENTICATED_ANONYMOUSLY\')'),
        new Put(security: 'is_granted(\'ROLE_CLIENT\')', denormalizationContext: ['groups' => ['product:write']]),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'IS_AUTHENTICATED_ANONYMOUSLY\')'),
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']]
)]
#[ApiFilter(OrderFilter::class, properties: ['id' => 'ASC', 'product' => 'ASC', 'price' => 'DESC'])]
#[ApiFilter(RandomOrderFilter::class)]
class Product
{
    /**
     * @var int
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"product_category:read","product:read","order_product:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]
    private $id;

    /**
     * @var string
     * @ORM\Column(name="product", type="string", length=255, nullable=false)
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['product' => 'partial'])]
    private $product;

    /**
     * @ORM\OneToMany(targetEntity="ProductFile", mappedBy="product")
     * @Groups({"product:read","product_category:read","order_product:read"})
     */
    #[ApiFilter(filterClass: ExistsFilter::class, properties: ['productFiles'])]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productFiles.file.fileType' => 'exact'])]
    private $productFiles;

    /**
     * @ORM\OneToMany(targetEntity="ProductCategory", mappedBy="product")
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productCategory.category' => 'exact'])]
    private $productCategory;

    /**
     * @var string|null
     * @ORM\Column(name="sku", type="string", length=32, nullable=true, options={"default"="NULL"})
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['sku' => 'partial'])]
    private $sku = null;

    /**
     * @var string
     * @ORM\Column(name="type", type="string", length=0, nullable=false, options={"default"="'product'"})
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['type' => 'exact'])]
    private $type = 'product';

    /**
     * @var float
     * @ORM\Column(name="price", type="float", precision=10, scale=0, nullable=false)
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    private $price = 0;

    /**
     * @var string
     * @ORM\Column(name="product_condition", type="string", length=0, nullable=false, options={"default"="'new'"})
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productCondition' => 'exact'])]
    private $productCondition = 'new';

    /**
     * @var string
     * @ORM\Column(name="description", type="string", length=0, nullable=false)
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    private $description = '';

    /**
     * @var bool
     * @ORM\Column(name="featured", type="boolean", nullable=false, options={"default"="0"})
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['featured' => 'exact'])]
    private $featured = false;

    /**
     * @var bool
     * @ORM\Column(name="active", type="boolean", nullable=false, options={"default"="1"})
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['active' => 'exact'])]
    private $active = true;

    /**
     * @var \ControleOnline\Entity\People
     * @ORM\ManyToOne(targetEntity="\ControleOnline\Entity\People")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="company_id", referencedColumnName="id")
     * })
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['company' => 'exact'])]
    private $company;

    /**
     * @var ProductUnity
     * @ORM\ManyToOne(targetEntity="ProductUnity")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="product_unit_id", referencedColumnName="id")
     * })
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    private $productUnit;

    /**
     * @var Queue
     * @ORM\ManyToOne(targetEntity="Queue")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="queue_id", referencedColumnName="id")
     * })
     * @Groups({"product_category:read","product:read","product_group_product:read","order_product:read","order_product_queue:read","order:read","order_details:read","order:write","product:write"})
     */
    private $queue;

    public function __construct()
    {
        $this->productFiles = new \Doctrine\Common\Collections\ArrayCollection();
        $this->productCategory = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
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
}
