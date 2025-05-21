<?php

namespace ControleOnline\Entity;

use Symfony\Component\Serializer\Attribute\Groups;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ControleOnline\Repository\ProductFileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')'),
        new Put(
            security: 'is_granted(\'ROLE_CLIENT\')',
            denormalizationContext: ['groups' => ['product_file:write']]
        ),
        new Delete(security: 'is_granted(\'ROLE_CLIENT\')'),
        new Post(securityPostDenormalize: 'is_granted(\'ROLE_CLIENT\')'),
        new GetCollection(security: 'is_granted(\'ROLE_ADMIN\') or is_granted(\'ROLE_CLIENT\')')
    ],
    formats: ['jsonld', 'json', 'html', 'jsonhal', 'csv' => ['text/csv']],
    normalizationContext: ['groups' => ['product_file:read']],
    denormalizationContext: ['groups' => ['product_file:write']]
)]
#[ApiFilter(filterClass: SearchFilter::class, properties: ['file' => 'exact', 'file.fileType' => 'exact', 'product' => 'exact'])]
#[ORM\Table(name: 'product_file')]
#[ORM\Index(name: 'file_id', columns: ['file_id'])]
#[ORM\Index(name: 'IDX_CDFC73564584665B', columns: ['product_id'])]
#[ORM\UniqueConstraint(name: 'product_id', columns: ['product_id', 'file_id'])]
#[ORM\Entity(repositoryClass: ProductFileRepository::class)]
class ProductFile
{
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[Groups(['product:read', 'order_product:read', 'order_details:read', 'product_file:read', 'product_category:read'])]
    private int $id = 0;

    #[ORM\JoinColumn(name: 'file_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: File::class)]
    #[Groups(['product:read', 'order_product:read', 'order_details:read', 'product_file:read', 'product_file:write', 'product_category:read'])]
    private File $file;

    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[Groups(['product_file:read', 'product_file:write'])]
    private Product $product;

    public function getId(): int
    {
        return $this->id;
    }

    public function getFile(): File
    {
        return $this->file;
    }

    public function setFile(File $file): self
    {
        $this->file = $file;
        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }
}
