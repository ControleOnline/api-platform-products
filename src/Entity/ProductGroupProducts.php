<?php

namespace ControleOnline\Products\Entity;

use Doctrine\ORM\Mapping as ORM;
use ControleOnline\Products\Entity\ProductGroup;
use ControleOnline\Products\Entity\Product;
/**
 * ProductGroupProducts
 *
 * @ORM\Table(name="product_group_products", uniqueConstraints={@ORM\UniqueConstraint(name="product_group", columns={"product_group", "product_relation", "product_id"})}, indexes={@ORM\Index(name="product_id", columns={"product_id"}), @ORM\Index(name="IDX_E9E36809CC9C3F99", columns={"product_group"})})
 * @ORM\Entity(repositoryClass="App\Repository\ProductGroupProductRepository")
 */
class ProductGroupProducts
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="product_relation", type="string", length=0, nullable=false)
     */
    private $productRelation;

    /**
     * @var int|null
     *
     * @ORM\Column(name="product_quantity", type="integer", nullable=true, options={"default"="1"})
     */
    private $productQuantity = 1;

    /**
     * @var bool
     *
     * @ORM\Column(name="active", type="boolean", nullable=false, options={"default"="1"})
     */
    private $active = true;

    /**
     * @var ProductGroup
     *
     * @ORM\ManyToOne(targetEntity="ProductGroup")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="product_group", referencedColumnName="id")
     * })
     */
    private $productGroup;

    /**
     * @var Product
     *
     * @ORM\ManyToOne(targetEntity="Product")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="product_id", referencedColumnName="id")
     * })
     */
    private $product;



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
     * Get the value of productRelation
     */
    public function getProductRelation(): string
    {
        return $this->productRelation;
    }

    /**
     * Set the value of productRelation
     */
    public function setProductRelation(string $productRelation): self
    {
        $this->productRelation = $productRelation;

        return $this;
    }

    /**
     * Get the value of productQuantity
     */
    public function getProductQuantity(): ?int
    {
        return $this->productQuantity;
    }

    /**
     * Set the value of productQuantity
     */
    public function setProductQuantity(?int $productQuantity): self
    {
        $this->productQuantity = $productQuantity;

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
     * Get the value of productGroup
     */
    public function getProductGroup(): ProductGroup
    {
        return $this->productGroup;
    }

    /**
     * Set the value of productGroup
     */
    public function setProductGroup(ProductGroup $productGroup): self
    {
        $this->productGroup = $productGroup;

        return $this;
    }

    /**
     * Get the value of product
     */
    public function getProduct(): Product
    {
        return $this->product;
    }

    /**
     * Set the value of product
     */
    public function setProduct(Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }
}
