<?php
namespace ControleOnline\Tests\Security;
use ControleOnline\Entity\{File,People,ProductFile};
use ControleOnline\Service\ProductMenuImageTrait;
use PHPUnit\Framework\{TestCase,Attributes\AllowMockObjectsWithoutExpectations};

#[AllowMockObjectsWithoutExpectations]
class ProductMenuImagePrivacyTest extends TestCase
{
    public function testPrivateAndForeignImagesAreNeverReadIntoPublicPdf(): void
    {
        $company=$this->createMock(People::class);$company->method('getId')->willReturn(7);
        $foreign=$this->createMock(People::class);$foreign->method('getId')->willReturn(9);
        $renderer=new class {
            use ProductMenuImageTrait;
            public function render(People $company, array $files): ?string { $this->catalogCompany=$company; return $this->resolveImageSource($files); }
        };
        foreach ([[false,$company],[true,$foreign]] as [$public,$owner]) {
            $file=$this->createMock(File::class);$file->method('isPublic')->willReturn($public);$file->method('getPeople')->willReturn($owner);
            $file->expects(self::never())->method('getContent');
            self::assertNull($renderer->render($company,[(new ProductFile())->setFile($file)]));
        }
    }
}
