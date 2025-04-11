<?php

namespace ControleOnline\Entity; 
use ControleOnline\Listener\LogListener;

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
#[ORM\Table(name: 'inventory')]
#[ORM\Index(name: 'people_id', columns: ['people_id'])]
#[ORM\Entity(repositoryClass: \ControleOnline\Repository\InventoryRepository::class)]
class Inventory
{
    /**
     * @var int
     * @Groups({"inventory:read", "inventory:write"})
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    /**
     * @var string
     * @Groups({"inventory:read", "inventory:write"})
     */
    #[ORM\Column(name: 'inventory', type: 'string', length: 255, nullable: false)]
    private $inventory;

    /**
     * @var string
     * @Groups({"inventory:read", "inventory:write"})
     */
    #[ORM\Column(name: 'type', type: 'string', length: 50, nullable: false)]
    private $type;

    /**
     * @var \ControleOnline\Entity\People
     * @Groups({"inventory:read", "inventory:write"})
     */
    #[ORM\JoinColumn(name: 'people_id', referencedColumnName: 'id', nullable: false)]
    #[ORM\ManyToOne(targetEntity: People::class)]
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