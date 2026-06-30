<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity]
#[ORM\Table(name: 'investment_product_documents')]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class InvestmentProductDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $productType = 'SCPI';

    #[ORM\Column(type: 'string', length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'string', length: 255)]
    private string $fileName = '';

    #[Assert\File(
        maxSize: '20M',
        mimeTypes: [
            'application/pdf', 'application/x-pdf',
            'image/png', 'image/jpeg',
            'application/octet-stream'
        ],
        maxSizeMessage: 'Le fichier ne doit pas dépasser 20 Mo',
        mimeTypesMessage: 'Formats acceptés: PDF, JPG, PNG'
    )]
    #[Vich\UploadableField(mapping: 'product_document', fileNameProperty: 'fileName')]
    private ?File $file = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public static function getAvailableProductTypes(): array
    {
        // Types principaux affichés dans la page Opportunités
        return [
            'SCPI' => 'SCPI',
            'PEA_PME' => 'PEA-PME',
            'ASSURANCE_VIE' => 'Assurance-vie',
            'PER' => 'PER',
        ];
    }

    public function __toString(): string
    {
        return $this->title ?: ('Document #' . ($this->id ?? ''));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductType(): string
    {
        return $this->productType;
    }

    public function setProductType(string $productType): self
    {
        $this->productType = $productType;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function setFile(?File $file): self
    {
        $this->file = $file;
        if ($file !== null) {
            $this->updatedAt = new \DateTime();
        }
        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}


