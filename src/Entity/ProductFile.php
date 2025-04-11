<?php

namespace ControleOnline\Entity; 
use ControleOnline\Listener\LogListener;

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


/**
 * ProductFile
 */
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
#[ORM\Table(name: 'product_file')]
#[ORM\Index(name: 'file_id', columns: ['file_id'])]
#[ORM\Index(name: 'IDX_CDFC73564584665B', columns: ['product_id'])]
#[ORM\UniqueConstraint(name: 'product_id', columns: ['product_id', 'file_id'])]
#[ORM\Entity(repositoryClass: \ControleOnline\Repository\ProductFileRepository::class)]
class ProductFile
{
    /**
     * @var int
     *
     * @Groups({"product:read","order_product:read","product_file:read","product_category:read"})
     */
    #[ORM\Column(name: 'id', type: 'integer', nullable: false)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private $id;

    /**
     * @var ControleOnline\Entity\File
     *
     * @Groups({"product:read","order_product:read","product_file:read","product_file:write","product_category:read"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['file' => 'exact'])]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['file.fileType' => 'exact'])]
    #[ORM\JoinColumn(name: 'file_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: \ControleOnline\Entity\File::class)]

    private $file;

    /**
     * @var Product
     *
     * @Groups({"product_file:read","product_file:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['product' => 'exact'])]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id')]
    #[ORM\ManyToOne(targetEntity: \Product::class)]

    private $product;

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the value of file
     */
    public function getFile(): File
    {
        return $this->file;
    }

    /**
     * Set the value of file
     */
    public function setFile(File $file): self
    {
        $this->file = $file;

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
}
