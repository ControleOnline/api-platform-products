<?php

namespace ControleOnline\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProductGroup
 *
 * @ORM\Table(name="product_group")
 * @ORM\Entity(repositoryClass="ControleOnline\Repository\ProductGroupRepository")
 */
class ProductGroup
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
     * @ORM\Column(name="product_group", type="string", length=255, nullable=false)
     */
    private $productGroup;

    /**
     * @var string
     *
     * @ORM\Column(name="price_calculation", type="string", length=0, nullable=false, options={"default"="'sum'"})
     */
    private $priceCalculation = 'sum';

    /**
     * @var bool
     *
     * @ORM\Column(name="required", type="boolean", nullable=false)
     */
    private $required = 0;

    /**
     * @var int|null
     *
     * @ORM\Column(name="minimum", type="integer", nullable=true, options={"default"="NULL"})
     */
    private $minimum = NULL;

    /**
     * @var int|null
     *
     * @ORM\Column(name="maximum", type="integer", nullable=true, options={"default"="NULL"})
     */
    private $maximum = NULL;

    /**
     * @var bool
     *
     * @ORM\Column(name="active", type="boolean", nullable=false, options={"default"="1"})
     */
    private $active = true;

    /**
     * @var int
     *
     * @ORM\Column(name="group_order", type="integer", nullable=false)
     */
    private $groupOrder;



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
}
