<?php

namespace ControleOnline\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\OneToMany;

/**
 * ProductGroup
 *
 * @ORM\Table(name="product_group")
 * @ORM\Entity(repositoryClass="ControleOnline\Repository\ProductGroupRepository")
 */

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')'),
        new Put(security: 'is_granted(\'ROLE_CLIENT\')', denormalizationContext: ['groups' => ['product_group_write']]),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')')
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product_group_read']],
    denormalizationContext: ['groups' => ['product_group_write']]
)]
class ProductGroup
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"product_group_read","product_group_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['id' => 'exact'])]
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="product_group", type="string", length=255, nullable=false)
     * @Groups({"product_group_read","product_group_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['productGroup' => 'exact'])]

    private $productGroup;

    /**
     * @var string
     *
     * @ORM\Column(name="price_calculation", type="string", length=0, nullable=false, options={"default"="'sum'"})
     * @Groups({"product_group_read","product_group_write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['priceCalculation' => 'exact'])]

    private $priceCalculation = 'sum';

    /**
     * @var bool
     *
     * @ORM\Column(name="required", type="boolean", nullable=false)
     * @Groups({"product_group_read","product_group_write"})
     */
    private $required = 0;

    /**
     * @var int|null
     *
     * @ORM\Column(name="minimum", type="integer", nullable=true, options={"default"="NULL"})
     * @Groups({"product_group_read","product_group_write"})
     */
    private $minimum = NULL;

    /**
     * @var int|null
     *
     * @ORM\Column(name="maximum", type="integer", nullable=true, options={"default"="NULL"})
     * @Groups({"product_group_read","product_group_write"})
     */
    private $maximum = NULL;

    /**
     * @var bool
     *
     * @ORM\Column(name="active", type="boolean", nullable=false, options={"default"="1"})
     * @Groups({"product_group_read","product_group_write"})
     */
    private $active = true;

    /**
     * @var int
     *
     * @ORM\Column(name="group_order", type="integer", nullable=false)
     * @Groups({"product_group_read","product_group_write"})
     */

    private $groupOrder;

    /**
     * @var Collection|ProductGroupProduct[]
     *
     * @ORM\OneToMany(targetEntity="ProductGroupProduct", mappedBy="productGroup", orphanRemoval=true)
     * @Groups({"product_group_read","product_group_write"})
     */
    private $products;

    public function __construct()
    {
        $this->products = new ArrayCollection();
    }

    /**
     * Get the value of id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the value of productGroup
     */
    public function getProductGroup(): string
    {
        return $this->productGroup;
    }

    /**
     * Set the value of productGroup
     */
    public function setProductGroup(string $productGroup): self
    {
        $this->productGroup = $productGroup;

        return $this;
    }

    /**
     * Get the value of priceCalculation
     */
    public function getPriceCalculation(): string
    {
        return $this->priceCalculation;
    }

    /**
     * Set the value of priceCalculation
     */
    public function setPriceCalculation(string $priceCalculation): self
    {
        $this->priceCalculation = $priceCalculation;

        return $this;
    }

    /**
     * Get the value of required
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Set the value of required
     */
    public function setRequired(bool $required): self
    {
        $this->required = $required;

        return $this;
    }

    /**
     * Get the value of minimum
     */
    public function getMinimum(): ?int
    {
        return $this->minimum;
    }

    /**
     * Set the value of minimum
     */
    public function setMinimum(?int $minimum): self
    {
        $this->minimum = $minimum;

        return $this;
    }

    /**
     * Get the value of maximum
     */
    public function getMaximum(): ?int
    {
        return $this->maximum;
    }

    /**
     * Set the value of maximum
     */
    public function setMaximum(?int $maximum): self
    {
        $this->maximum = $maximum;

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
     * Get the value of groupOrder
     */
    public function getGroupOrder(): int
    {
        return $this->groupOrder;
    }

    /**
     * Set the value of groupOrder
     */
    public function setGroupOrder(int $groupOrder): self
    {
        $this->groupOrder = $groupOrder;

        return $this;
    }

    public function getRequired(): ?bool
    {
        return $this->required;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    /**
     * @return Collection|ProductGroupProduct[]
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(ProductGroupProduct $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products[] = $product;
            $product->setProductGroup($this);
        }

        return $this;
    }

    public function removeProduct(ProductGroupProduct $product): self
    {
        if ($this->products->removeElement($product)) {
            // set the owning side to null (unless already changed)
            if ($product->getProductGroup() === $this) {
                $product->setProductGroup(null);
            }
        }

        return $this;
    }
}
