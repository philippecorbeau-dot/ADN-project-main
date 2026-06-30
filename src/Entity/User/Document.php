<?php

namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_document')]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]

class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\Column(name: 'identity_card_filename', type: 'string', length: 255, nullable: true)]
    private $identityCardFilename;

    #[Assert\File(
        maxSize: '8192k',
        mimeTypes: ['image/png', 'image/jpeg', 'application/pdf', 'application/x-pdf', 'application/octet-stream'],
        maxSizeMessage: 'Le fichier ne doit pas peser plus de 8Mo',
        mimeTypesMessage: 'Merci d\'envoyer un document au format .JPG, .PNG ou .PDF'
    )]
    #[Vich\UploadableField(mapping: 'user_document', fileNameProperty: 'identityCardFilename')]
    private $identityCardFile;

    #[ORM\Column(name: 'proof_address_filename', type: 'string', length: 255, nullable: true)]
    private $proofAddressFilename;

    #[Assert\File(
        maxSize: '8192k',
        mimeTypes: ['image/png', 'image/jpeg', 'application/pdf', 'application/x-pdf', 'application/octet-stream'],
        maxSizeMessage: 'Le fichier ne doit pas peser plus de 8Mo',
        mimeTypesMessage: 'Merci d\'envoyer un document au format .JPG, .PNG ou .PDF'
    )]
    #[Vich\UploadableField(mapping: 'user_document', fileNameProperty: 'proofAddressFilename')]
    private $proofAddressFile;

    #[ORM\Column(name: 'articles_of_association_filename', type: 'string', length: 255, nullable: true)]
    private $articlesOfAssociationFilename;

    #[Assert\File(
        maxSize: '8192k',
        mimeTypes: ['image/png', 'image/jpeg', 'application/pdf', 'application/x-pdf', 'application/octet-stream'],
        maxSizeMessage: 'Le fichier ne doit pas peser plus de 8Mo',
        mimeTypesMessage: 'Merci d\'envoyer un document au format .JPG, .PNG ou .PDF'
    )]
    #[Vich\UploadableField(mapping: 'user_document', fileNameProperty: 'articlesOfAssociationFilename')]
    private $articlesOfAssociationFile;

    #[ORM\Column(name: 'registration_proof_filename', type: 'string', length: 255, nullable: true)]
    private $registrationProofFilename;

    #[Assert\File(
        maxSize: '8192k',
        mimeTypes: ['image/png', 'image/jpeg', 'application/pdf', 'application/x-pdf', 'application/octet-stream'],
        maxSizeMessage: 'Le fichier ne doit pas peser plus de 8Mo',
        mimeTypesMessage: 'Merci d\'envoyer un document au format .JPG, .PNG ou .PDF'
    )]
    #[Vich\UploadableField(mapping: 'user_document', fileNameProperty: 'registrationProofFilename')]
    private $registrationProofFile;

    #[ORM\Column(name: 'share_holder_declaration_filename', type: 'string', length: 255, nullable: true)]
    private $shareHolderDeclarationFilename;

    #[Assert\File(
        maxSize: '8192k',
        mimeTypes: ['image/png', 'image/jpeg', 'application/pdf', 'application/x-pdf', 'application/octet-stream'],
        maxSizeMessage: 'Le fichier ne doit pas peser plus de 8Mo',
        mimeTypesMessage: 'Merci d\'envoyer un document au format .JPG, .PNG ou .PDF'
    )]
    #[Vich\UploadableField(mapping: 'user_document', fileNameProperty: 'shareHolderDeclarationFilename')]
    private $shareHolderDeclarationFile;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private $updatedAt;

    #[ORM\Column(name: 'confirmation_token', type: 'string')]
    private $confirmationToken;

    #[ORM\Column(name: 'is_pro', type: 'boolean', nullable: true)]
    private $isPro;


    public function __toString(): string
    {
        return 'Documents';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setIdentityCardFilename(string $identityCardFilename): Document
    {
        $this->identityCardFilename = $identityCardFilename;

        return $this;
    }

    public function getIdentityCardFilename(): string
    {
        return $this->identityCardFilename;
    }

    public function setProofAddressFilename(string $proofAddressFilename): Document
    {
        $this->proofAddressFilename = $proofAddressFilename;

        return $this;
    }

    public function getProofAddressFilename(): string
    {
        return $this->proofAddressFilename;
    }

    public function setCreatedAt(\DateTime $createdAt): Document
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): Document
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

        if ($this->getCreatedAt() == null) {
            $this->setCreatedAt(new \DateTime('now'));
        }
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updatedConfirmationToken(): void
    {
        if ($this->getConfirmationToken() == null) {
            $this->setConfirmationToken(bin2hex(random_bytes(35)));
        }
    }

    public function setIdentityCardFile(File $identityCardFile = null): Document
    {
        $this->identityCardFile = $identityCardFile;

        if ($identityCardFile) {
            $this->updatedAt = new \DateTime('now');
        }

        return $this;
    }

    public function getIdentityCardFile(): File
    {
        return $this->identityCardFile;
    }

    public function setProofAddressFile(File $proofAddressFile = null): Document
    {
        $this->proofAddressFile = $proofAddressFile;

        if ($proofAddressFile) {
            $this->updatedAt = new \DateTime('now');
        }

        return $this;
    }

    public function getProofAddressFile(): File
    {
        return $this->proofAddressFile;
    }

    public function setConfirmationToken(string $confirmationToken): Document
    {
        $this->confirmationToken = $confirmationToken;

        return $this;
    }

    public function getConfirmationToken(): string
    {
        return $this->confirmationToken;
    }

    public function setArticlesOfAssociationFilename(string $articlesOfAssociationFilename): Document
    {
        $this->articlesOfAssociationFilename = $articlesOfAssociationFilename;

        return $this;
    }

    public function getArticlesOfAssociationFilename(): string
    {
        return $this->articlesOfAssociationFilename;
    }

    public function setArticlesOfAssociationFile(File $articlesOfAssociationFile = null): Document
    {
        $this->articlesOfAssociationFile = $articlesOfAssociationFile;

        if ($articlesOfAssociationFile) {
            $this->updatedAt = new \DateTime('now');
        }

        return $this;
    }

    public function getArticlesOfAssociationFile(): File
    {
        return $this->articlesOfAssociationFile;
    }

    public function setRegistrationProofFilename(string $registrationProofFilename): Document
    {
        $this->registrationProofFilename = $registrationProofFilename;

        return $this;
    }

    public function getRegistrationProofFilename(): string
    {
        return $this->registrationProofFilename;
    }

    public function setRegistrationProofFile(File $registrationProofFile = null): Document
    {
        $this->registrationProofFile = $registrationProofFile;

        if ($registrationProofFile) {
            $this->updatedAt = new \DateTime('now');
        }

        return $this;
    }

    public function getRegistrationProofFile(): File
    {
        return $this->registrationProofFile;
    }

    public function setShareHolderDeclarationFilename(string $shareHolderDeclarationFilename): Document
    {
        $this->shareHolderDeclarationFilename = $shareHolderDeclarationFilename;

        return $this;
    }

    public function getShareHolderDeclarationFilename(): string
    {
        return $this->shareHolderDeclarationFilename;
    }

    public function setShareHolderDeclarationFile(File $shareHolderDeclarationFile = null): Document
    {
        $this->shareHolderDeclarationFile = $shareHolderDeclarationFile;

        if ($shareHolderDeclarationFile) {
            $this->updatedAt = new \DateTime('now');
        }

        return $this;
    }

    public function getShareHolderDeclarationFile(): File
    {
        return $this->shareHolderDeclarationFile;
    }

    public function setIsPro($isPro): Document
    {
        $this->isPro = $isPro;

        return $this;
    }

    public function getIsPro(): bool
    {
        return $this->isPro;
    }

    /**
     * @ORM\PrePersist()
     */
    public function updateConfirmationToken(): string
    {
        $this->setConfirmationToken(bin2hex(random_bytes(35)));
    }

}