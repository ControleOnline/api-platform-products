<?php
namespace ControleOnline\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use ControleOnline\Entity\People;
use ControleOnline\Repository\InventoryRepository;
use ControleOnline\Listener\LogListener;

#[ORM\Table(name: 'inventory')]
#[ORM\Index(name: 'people_id', columns: ['people_id'])]
#[ORM\Entity(repositoryClass: InventoryRepository::class)]
#[ApiResource(
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => 'text/csv'],
    normalizationContext: ['groups' => ['inventory:read']],
    denormalizationContext: ['groups' => ['inventory:write']],
    operations: [
        new GetCollection(security: "is_granted('PUBLIC_ACCESS')"),
        new Get(security: "is_granted('PUBLIC_ACCESS')"),
        new Post(securityPostDenormalize: "is_granted('ROLE_CLIENT')"),
        new Put(
            security: "is_granted('ROLE_CLIENT')",
            denormalizationContext: ['groups' => ['inventory:write']]
        ),
        new Delete(security: "is_granted('ROLE_CLIENT')")
    ]
)]
#[ApiFilter(OrderFilter::class, properties: ['inventory', 'type'])]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'inventory' => 'partial',
    'type' => 'exact',
    'people' => 'exact'
])]
class Inventory
{
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['inventory:read', 'inventory:write'])]
    private $id;

    #[ORM\Column(name: 'inventory', type: 'string', length: 255, nullable: false)]
    #[Groups(['inventory:read', 'inventory:write'])]
    private $inventory;

    #[ORM\Column(name: 'type', type: 'string', length: 50, nullable: false)]
    #[Groups(['inventory:read', 'inventory:write'])]
    private $type;

    #[ORM\ManyToOne(targetEntity: People::class)]
    #[ORM\JoinColumn(name: 'people_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['inventory:read', 'inventory:write'])]
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