<?php

namespace ControleOnline\Entity;

use Doctrine\ORM\Mapping as ORM;
use ControleOnline\Entity\People;
use ControleOnline\Entity\ProductUnity;


use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiFilter;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;



/**
 * Product
 *
 * @ORM\Table(name="product", uniqueConstraints={@ORM\UniqueConstraint(name="company_id", columns={"company_id", "sku"})}, indexes={@ORM\Index(name="product_unit_id", columns={"product_unit_id"}), @ORM\Index(name="IDX_D34A04AD979B1AD6", columns={"company_id"})})
 * @ORM\Entity(repositoryClass="ControleOnline\Repository\ProductRepository")
 */

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')'),
        new Put(security: 'is_granted(\'ROLE_CLIENT\')', denormalizationContext: ['groups' => ['product_write']]),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')')
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product_read']],
    denormalizationContext: ['groups' => ['product_write']]
)]


class Product
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"product_read"})

     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="product", type="string", length=255, nullable=false)
     * @Groups({"product_read","order_product_read","order_read","product_write"})
     */
    private $product;

    /**
     * @var string|null
     *
     * @ORM\Column(name="sku", type="string", length=32, nullable=true, options={"default"="NULL"})
     * @Groups({"product_read","order_product_read","order_read","product_write"})

     */
    private $sku = NULL;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=0, nullable=false, options={"default"="'product'"})
     * @Groups({"product_read","order_product_read","order_read","product_write"})

     */
    private $type = 'product';

    /**
     * @var float
     *
     * @ORM\Column(name="price", type="float", precision=10, scale=0, nullable=false)
     * @Groups({"product_read","order_product_read","order_read","product_write"})

     */
    private $price = 0;

    /**
     * @var string
     *
     * @ORM\Column(name="product_condition", type="string", length=0, nullable=false, options={"default"="'new'"})
     * @Groups({"product_read","order_product_read","order_read","product_write"})

     */
    private $productCondition = 'new';


    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=0, nullable=false)
     * @Groups({"product_read","order_product_read","order_read","product_write"})

     */
    private $description = '';

    /**
     * @var bool
     *
     * @ORM\Column(name="active", type="boolean", nullable=false, options={"default"="1"})
     * @Groups({"product_read","order_product_read","order_read","product_write"})

     */
    private $active = true;

    /**
     * @var \ControleOnline\Entity\People
     *
     * @ORM\ManyToOne(targetEntity="\ControleOnline\Entity\People")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="company_id", referencedColumnName="id")
     * })
     * @Groups({"product_read","order_product_read","order_read","product_write"})

     */
    private $company;

    /**
     * @var ProductUnity
     *
     * @ORM\ManyToOne(targetEntity="ProductUnity")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="product_unit_id", referencedColumnName="id")
     * })
     * @Groups({"product_read","order_product_read","order_read","product_write"})
     */
    private $productUnit;

    /**
     * Get the value of id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set the value of id
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of product
     */
    public function getProduct(): string
    {
        return $this->product;
    }

    /**
     * Set the value of product
     */
    public function setProduct(string $product): self
    {
        $this->product = $product;

        return $this;
    }

    /**
     * Get the value of sku
     */
    public function getSku(): ?string
    {
        return $this->sku;
    }

    /**
     * Set the value of sku
     */
    public function setSku(?string $sku): self
    {
        $this->sku = $sku;

        return $this;
    }

    /**
     * Get the value of type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set the value of type
     */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get the value of price
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * Set the value of price
     */
    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    /**
     * Get the value of productCondition
     */
    public function getProductCondition(): string
    {
        return $this->productCondition;
    }

    /**
     * Set the value of productCondition
     */
    public function setProductCondition(string $productCondition): self
    {
        $this->productCondition = $productCondition;

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
     * Get the value of company
     */
    public function getCompany(): ?People
    {
        return $this->company;
    }

    /**
     * Set the value of company
     */
    public function setCompany(People $company): self
    {
        $this->company = $company;

        return $this;
    }

    /**
     * Get the value of productUnit
     */
    public function getProductUnit(): ProductUnity
    {
        return $this->productUnit;
    }

    /**
     * Set the value of productUnit
     */
    public function setProductUnit(ProductUnity $productUnit): self
    {
        $this->productUnit = $productUnit;

        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    /**
     * Get the value of description
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set the value of description
     */
    public function setDescription($description): self
    {
        $this->description = $description;

        return $this;
    }
}
