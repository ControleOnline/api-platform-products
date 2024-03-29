<?php

namespace ControleOnline\Entity;

use Doctrine\ORM\Mapping as ORM;



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
 * ProductUnity
 *
 * @ORM\Table(name="product_unity")
 * @ORM\Entity(repositoryClass="ControleOnline\Repository\ProductUnityRepository")
 */

 #[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')'),
        new Put(security: 'is_granted(\'ROLE_CLIENT\')', denormalizationContext: ['groups' => ['product_unity_edit']]),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')')
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product_unity_read']],
    denormalizationContext: ['groups' => ['product_unity_write']]
)]
class ProductUnity
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"product_read","product_group_read","product_unity_read"})

     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="product_unit", type="string", length=3, nullable=false)
     * @Groups({"product_read","product_group_read","product_unity_read"})

     */
    private $productUnit;

    /**
     * @var string
     *
     * @ORM\Column(name="unit_type", type="string", length=0, nullable=false, options={"default"="'I'","comment"="Integer, Fractioned"})
     * @Groups({"product_read","product_group_read","product_unity_read"})
     */
    private $unitType = 'I';

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
     * Get the value of productUnit
     */
    public function getProductUnit(): string
    {
        return $this->productUnit;
    }

    /**
     * Set the value of productUnit
     */
    public function setProductUnit(string $productUnit): self
    {
        $this->productUnit = $productUnit;

        return $this;
    }

    /**
     * Get the value of unitType
     */
    public function getUnitType(): string
    {
        return $this->unitType;
    }

    /**
     * Set the value of unitType
     */
    public function setUnitType(string $unitType): self
    {
        $this->unitType = $unitType;

        return $this;
    }
}
