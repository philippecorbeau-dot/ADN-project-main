<?php

namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_kyc_document_file')]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]

class KycDocumentFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\Column(name: 'createdAt', type: 'datetime')]
    private $createdAt;

    #[ORM\Column(name: 'updatedAt', type: 'datetime')]
    private $updatedAt;

    #[Assert\File(
        maxSize: '8192k',
        mimeTypes: ['image/png', 'image/jpeg', 'application/pdf', 'application/x-pdf', 'application/octet-stream'],
        maxSizeMessage: 'Le fichier ne doit pas peser plus de 8Mo',
        mimeTypesMessage: 'Merci d\'envoyer un document au format .JPG, .PNG ou .PDF'
    )]
    #[Vich\UploadableField(mapping: 'user_document', fileNameProperty: 'name')]
    private $file;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    private $name;

    #[ORM\ManyToOne(targetEntity: KycDocument::class, cascade: ['persist', 'remove'], inversedBy: 'files')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private $document;

    #[ORM\Column(name: 'is_image', type: 'boolean')]
    private $isImage = false;


    public function __toString(): string
    {
        return (string) $this->getName();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setName(string $name): KycDocumentFile
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setFile(File $file = null): KycDocumentFile
    {
        $this->file = $file;

        if ($file) {
            $this->updatedAt = new \DateTime('now');
        }

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setCreatedAt(\DateTime $createdAt): KycDocumentFile
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): KycDocumentFile
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updatedTimestamps(): void
    {
        $this->setUpdatedAt(new \DateTime('now'));

        if ($this->getCreatedAt() == NULL) {
            $this->setCreatedAt(new \DateTime('now'));
        }
    }

    public function setDocument(KycDocument $document = null): KycDocumentFile
    {
        // $document->addFile($this); // Is this dangerous ? Infinte loop ?
        $this->document = $document;

        return $this;
    }

    public function getDocument(): KycDocument
    {
        return $this->document;
    }

    public function setIsImage(bool $isImage): KycDocumentFile
    {
        $this->isImage = $isImage;

        return $this;
    }

    public function getIsImage(): bool
    {
        return $this->isImage;
    }

    /**
     * @ORM\PrePersist()
     */
    public function upload()
    {
        if (!empty($this->getFile())) {
            $this->setIsImage($this->isImage($this->getFile()));
        }
    }

    private function isImage($file): bool
    {
        $type = explode("/", $file->getMimeType());

        return $type[0] == 'image';
    }
}
