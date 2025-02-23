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


/**
 * ProductFile
 *
 * @ORM\Table(name="product_file", uniqueConstraints={@ORM\UniqueConstraint(name="product_id", columns={"product_id", "file_id"})}, indexes={@ORM\Index(name="file_id", columns={"file_id"}), @ORM\Index(name="IDX_CDFC73564584665B", columns={"product_id"})})
 * @ORM\Entity(repositoryClass="ControleOnline\Repository\ProductFileRepository")
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
class ProductFile
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @Groups({"product_details:read","product_file:read"})
     */
    private $id;

    /**
     * @var ControleOnline\Entity\File
     *
     * @ORM\ManyToOne(targetEntity="ControleOnline\Entity\File")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="file_id", referencedColumnName="id")
     * })
     * @Groups({"product_details:read","product_file:read","product_file:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['file' => 'exact'])]
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['file.fileType' => 'exact'])]

    private $file;

    /**
     * @var Product
     *
     * @ORM\ManyToOne(targetEntity="Product")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="product_id", referencedColumnName="id")
     * })
     * @Groups({"product_file:read","product_file:write"})
     */
    #[ApiFilter(filterClass: SearchFilter::class, properties: ['product' => 'exact'])]

    private $product;

    /**
     * Get the value of id
     */
    public function getId(): int
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
