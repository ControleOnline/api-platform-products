<?php

namespace ControleOnline\Entity;

use Doctrine\ORM\Mapping as ORM;
use ControleOnline\Entity\People;
use Symfony\Component\Serializer\Annotation\Groups;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;

/**
 * Inventory
 *
 * @ORM\Table(name="inventory", indexes={@ORM\Index(name="people_id", columns={"people_id"})})
 * @ORM\Entity(repositoryClass="ControleOnline\Repository\InventoryRepository")
 */
#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'IS_AUTHENTICATED_ANONYMOUSLY\')'),
        new Put(security: 'is_granted(\'ROLE_CLIENT\')', denormalizationContext: ['groups' => ['inventory:write']]),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'IS_AUTHENTICATED_ANONYMOUSLY\')'),
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['inventory:read']],
    denormalizationContext: ['groups' => ['inventory:write']]
)]
#[ApiFilter(OrderFilter::class, properties: ['inventory', 'type'])]
#[ApiFilter(SearchFilter::class, properties: ['id' => 'exact', 'inventory' => 'partial', 'type' => 'exact', 'people' => 'exact'])]
class Inventory
{
    /**
     * @var int
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"inventory:read", "inventory:write"})
     */
    private $id;

    /**
     * @var string
     * @ORM\Column(name="inventory", type="string", length=255, nullable=false)
     * @Groups({"inventory:read", "inventory:write"})
     */
    private $inventory;

    /**
     * @var string
     * @ORM\Column(name="type", type="string", length=50, nullable=false)
     * @Groups({"inventory:read", "inventory:write"})
     */
    private $type;

    /**
     * @var \ControleOnline\Entity\People
     * @ORM\ManyToOne(targetEntity="\ControleOnline\Entity\People")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="people_id", referencedColumnName="id", nullable=false)
     * })
     * @Groups({"inventory:read", "inventory:write"})
     */
    private $people;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getInventory(): ?string
    {
        return $this->inventory;
    }

    public function setInventory(string $inventory): self
    {
        $this->inventory = $inventory;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getPeople(): ?People
    {
        return $this->people;
    }

    public function setPeople(People $people): self
    {
        $this->people = $people;
        return $this;
    }
}