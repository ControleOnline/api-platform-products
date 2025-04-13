<?php

namespace ControleOnline\Entity;

use Symfony\Component\Serializer\Attribute\Groups;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ControleOnline\Listener\LogListener;
use ControleOnline\Repository\ProductUnityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')'),
        new Put(security: 'is_granted(\'ROLE_CLIENT\')', denormalizationContext: ['groups' => ['product_unity_edit']]),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')')
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product_unity:read']],
    denormalizationContext: ['groups' => ['product_unity:write']]
)]
#[ORM\Table(name: 'product_unity')]
#[ORM\Entity(repositoryClass: ProductUnityRepository::class)]
class ProductUnity
{
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['product:read', 'product_group_product:read', 'product_group:read', 'product_unity:read'])]
    private $id;

    #[ORM\Column(name: 'product_unit', type: 'string', length: 3, nullable: false)]
    #[Groups(['product:read', 'product_group_product:read', 'product_group:read', 'product_unity:read'])]
    private $productUnit;

    #[ORM\Column(name: 'unit_type', type: 'string', length: 0, nullable: false, options: ['default' => "'I'", 'comment' => 'Integer, Fractioned'])]
    #[Groups(['product:read', 'product_group_product:read', 'product_group:read', 'product_unity:read'])]
    private $unitType = 'I';

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getProductUnit(): string
    {
        return $this->productUnit;
    }

    public function setProductUnit(string $productUnit): self
    {
        $this->productUnit = $productUnit;
        return $this;
    }

    public function getUnitType(): string
    {
        return $this->unitType;
    }

    public function setUnitType(string $unitType): self
    {
        $this->unitType = $unitType;
        return $this;
    }
}