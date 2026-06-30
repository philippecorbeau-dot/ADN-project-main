<?php

namespace App\Entity\User;

use App\Repository\User\KycDocumentRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Entity\User\User;

#[ORM\Table(name: 'user_kyc_document')]
#[ORM\Entity(repositoryClass: KycDocumentRepository::class)]
#[Vich\Uploadable]
#[ORM\HasLifecycleCallbacks]

class KycDocument
{
    use User\KycDocumentFields;

    const STATUS_CREATED = 'CREATED';
    const STATUS_VALIDATION_ASKED = 'VALIDATION_ASKED';
    const STATUS_VALIDATED = 'VALIDATED';
    const STATUS_REFUSED = 'REFUSED';
    const STATUS_OUTDATED = 'OUT_OF_DATE';
    const STATUS_PENDING = 'PENDING';

    const DOCUMENT_TYPE_IDENTITY_PROOF = 'IDENTITY_PROOF';
    const DOCUMENT_TYPE_REGISTRATION_PROOF = 'REGISTRATION_PROOF';
    const DOCUMENT_TYPE_ARTICLES_OF_ASSOCIATION = 'ARTICLES_OF_ASSOCIATION';
    const DOCUMENT_TYPE_SHAREHOLDER_DECLARATION = 'SHAREHOLDER_DECLARATION';
    const DOCUMENT_TYPE_ADDRESS_PROOF = 'ADDRESS_PROOF';

    const DOCUMENT_ERROR_MSG_UNREADABLE                = 'DOCUMENT_UNREADABLE';
    const DOCUMENT_ERROR_MSG_NOT_ACCEPTED              = 'DOCUMENT_NOT_ACCEPTED';
    const DOCUMENT_ERROR_MSG_HAS_EXPIRED               = 'DOCUMENT_HAS_EXPIRED';
    const DOCUMENT_ERROR_MSG_INCOMPLETE                = 'DOCUMENT_INCOMPLETE';
    const DOCUMENT_ERROR_MSG_MISSING                   = 'DOCUMENT_MISSING';
    const DOCUMENT_ERROR_MSG_DO_NOT_MATCH_USER_DATA    = 'DOCUMENT_DO_NOT_MATCH_USER_DATA';
    const DOCUMENT_ERROR_MSG_DO_NOT_MATCH_ACCOUNT_DATA = 'DOCUMENT_DO_NOT_MATCH_ACCOUNT_DATA';

    const DOCUMENT_ERROR_MSG_FALSIFIED                 = 'DOCUMENT_FALSIFIED';
    const DOCUMENT_ERROR_MSG_UNDERAGE                  = 'UNDERAGE_PERSON';
    const DOCUMENT_ERROR_MSG_SPECIFIC_CASE             = 'SPECIFIC_CASE';

    const TYPE_LIST = [
        self::DOCUMENT_TYPE_IDENTITY_PROOF => "Carte d'identité",
        self::DOCUMENT_TYPE_REGISTRATION_PROOF => 'KBIS',
        self::DOCUMENT_TYPE_ARTICLES_OF_ASSOCIATION => 'Statuts',
        self::DOCUMENT_TYPE_SHAREHOLDER_DECLARATION => 'Déclaration des bénéficiaires effectifs',
        self::DOCUMENT_TYPE_ADDRESS_PROOF => 'Justificatif de domicile',
    ];

    const STATUS_LIST = [
        self::STATUS_CREATED => 'Créé',
        self::STATUS_VALIDATION_ASKED => 'En cours (validation homunity)',
        self::STATUS_VALIDATED => 'Validé',
        self::STATUS_REFUSED => 'Refusé',
        self::STATUS_OUTDATED => 'Expiré',
    ];

    const REFUSED_REASON_MESSAGE_LIST =  [
        // Codes internes (constantes)
        self::DOCUMENT_ERROR_MSG_UNREADABLE                => "Le document n'est pas lisible",
        self::DOCUMENT_ERROR_MSG_NOT_ACCEPTED              => "Le type de document n'est pas accepté",
        self::DOCUMENT_ERROR_MSG_HAS_EXPIRED               => 'Le document est expiré',
        self::DOCUMENT_ERROR_MSG_INCOMPLETE                => 'Document incomplet',
        self::DOCUMENT_ERROR_MSG_MISSING                   => 'Document manquant',
        self::DOCUMENT_ERROR_MSG_DO_NOT_MATCH_USER_DATA    => 'Le document ne correspond pas avec vos informations personnelles',
        self::DOCUMENT_ERROR_MSG_DO_NOT_MATCH_ACCOUNT_DATA => 'Le document ne correspond pas avec les informations du compte',
        self::DOCUMENT_ERROR_MSG_FALSIFIED                 => 'Le document semble falsifié',
        self::DOCUMENT_ERROR_MSG_UNDERAGE                  => 'Vous êtes mineur(e)',
        self::DOCUMENT_ERROR_MSG_SPECIFIC_CASE             => 'Cas spécifique indéterminé',

        // Synonymes/équivalents provenant d'anciennes valeurs (snake_case)
        'unreadable'                   => "Le document n'est pas lisible",
        'not_accepted'                 => "Le type de document n'est pas accepté",
        'has_expired'                  => 'Le document est expiré',
        'incomplete'                   => 'Document incomplet',
        'missing'                      => 'Document manquant',
        'do_not_match_user_data'       => 'Le document ne correspond pas avec vos informations personnelles',
        'do_not_match_account_data'    => 'Le document ne correspond pas avec les informations du compte',
        'falsified'                    => 'Le document semble falsifié',
        'underage_person'              => 'Vous êtes mineur(e)',
        'specific_case'                => 'Cas spécifique indéterminé',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\Column(name: 'createdAt', type: 'datetime')]
    private $createdAt;

    #[ORM\Column(name: 'updatedAt', type: 'datetime')]
    private $updatedAt;

    #[ORM\Column(name: 'type', type: 'string', length: 155)]
    private $type;

    #[ORM\ManyToOne(targetEntity: 'User', cascade: ['persist'], inversedBy: 'kycDocuments')]
    private $user;

    #[ORM\OneToMany(targetEntity: KycDocumentFile::class, mappedBy: 'document', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private $files;

    private $uploadedFile;

    #[ORM\Column(name: 'status', type: 'string', length: 55, nullable: true)]
    private $status = self::STATUS_CREATED;

    #[ORM\Column(name: 'refused_reason_message', type: 'string', length: 255, nullable: true)]
    private $refusedReasonMessage;

    #[ORM\Column(name: 'filename', type: 'string', length: 255, nullable: true)]
    private $filename;

    #[ORM\Column(name: 'uploaded_at', type: 'datetime', nullable: true)]
    private $uploadedAt;

    #[ORM\Column(name: 'expiration_date', type: 'date', nullable: true)]
    private $expirationDate;

    // Nouveau: traçabilité de la signature KYC
    #[ORM\Column(name: 'ip_address', type: 'string', length: 45, nullable: true)]
    private $ipAddress;

    #[ORM\Column(name: 'signed_at', type: 'datetime', nullable: true)]
    private $signedAt;

    public function __construct()
    {
        $this->files = new ArrayCollection();
    }

    public function __toString(): string
    {
        return 'Document ' . $this->getId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setCreatedAt(\DateTime $createdAt): KycDocument
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): KycDocument
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setType(string $type): KycDocument
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getTypeList(): array
    {
        return self::TYPE_LIST;
    }

    public function getTypeName(): string
    {
        return self::TYPE_LIST[$this->type];
    }

    public function getLiteralType(): string
    {
        $types = $this->getTypeList();

        return  $types[$this->getType()];
    }

    public function setUser(User $user = null): KycDocument
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getEmail(): string
    {
        return $this->user->getEmail();
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function onCreateAndUpdate()
    {
        $this->setUpdatedAt(new \DateTime('now'));

        if ($this->getCreatedAt() == NULL) {
            $this->setCreatedAt(new \DateTime('now'));
        }
    }

    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @ORM\PreFlush()
     */
    public function upload()
    {
        if (!empty($this->getUploadedFile()) and $this->getUploadedFile()->isFile()) {
            $kycDoc = new KycDocumentFile();
            $kycDoc->setFile($this->getUploadedFile());
            $this->addFile($kycDoc);
        }
    }

    public function addFile(KycDocumentFile $file): KycDocument
    {
        $file->setDocument($this);
        $this->files[] = $file;

        return $this;
    }

    public function removeFile(KycDocumentFile $file): void
    {
        $this->files->removeElement($file);
    }

    public function setUploadedFile(UploadedFile $uploadedFile): KycDocument
    {
        $this->uploadedFile = $uploadedFile;

        return $this;
    }

    public function getUploadedFile(): ?UploadedFile
    {
        return $this->uploadedFile;
    }

    public function setStatus(?string $status): KycDocument
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getStatusList(): array
    {
        return self::STATUS_LIST;
    }

    public function getStatusName(): ?string
    {
        if ($this->status) {
            return self::STATUS_LIST[$this->status];
        }

        return null;
    }

    public function setRefusedReasonMessage(string $refusedReasonMessage): KycDocument
    {
        $this->refusedReasonMessage = $refusedReasonMessage;

        return $this;
    }

    public function getRefusedReasonMessage(): ?string
    {
        return $this->refusedReasonMessage;
    }

    public function getRefusedReasonMessageList(): array
    {
        return self::REFUSED_REASON_MESSAGE_LIST;
    }

    public function getRefusedReasonMessageName(): ?string
    {
        if ($this->refusedReasonMessage) {
            return self::REFUSED_REASON_MESSAGE_LIST[$this->refusedReasonMessage];
        }

        return null;
    }

    public function statusIsCreated()
    {
        return $this->status === self::STATUS_CREATED;
    }

    public function statusIsValidationAsked()
    {
        return $this->status === self::STATUS_VALIDATION_ASKED;
    }

    public function statusIsValidated()
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function statusIsRefused()
    {
        return $this->status === self::STATUS_REFUSED;
    }

    public function statusIsOutdated()
    {
        return $this->status === self::STATUS_OUTDATED;
    }

    /**
     * The file is set after validation as it sent via ajax
     * So we validate before the form validation so it does not enter the validation process if the file has an error.
     */
    public function preValidate(?UploadedFile $uploadedFile): ?bool
    {
        $types = [
            'image/bmp',
            'image/gif',
            'image/jpg',
            'image/jpeg',
            'image/png',
            'image/pdf',
            'application/acrobat',
            'application/pdf',
        ];
        

        if ($uploadedFile) {
            try {
                if (!in_array($uploadedFile->getMimeType(), $types)) {
                    return false;
                }
                
                if($uploadedFile->getSize() < 36000 || $uploadedFile->getSize() > 10000000) {
                    return false;
                }
                
            } catch (\Exception $e) {
                /**
                 * Sometimes causes a:
                 *  - Uncaught PHP Exception Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException:
                 *  - "The file "" does not exist" at /home/site/production/shared/vendor/symfony/http-foundation/File/MimeType/MimeTypeGuesser.php
                 *  - line 116
                 *
                 * Remove try/catch to try to solve this problem
                 * OR if KYC upload has a bug and we don't know where it comes from
                 */
            }
        }

        return true;
    }

    public function hasUploadedKycDocumentWithSameTypeAndStatus(User $user, string $type, string $status): bool
    {
        foreach ($user->getKycDocuments() as $kycDocument) {
            if ($kycDocument->getType() === $type && $kycDocument->getStatus() === $status) {
                return true;
            }
        }

        return false;
    }

    public function getKycDocumentWithSameTypeAndStatus(User $user, string $type, string $status): ?KycDocument
    {
        foreach ($user->getKycDocuments() as $kycDocument) {
            if ($kycDocument->getType() === $type && $kycDocument->getStatus() === $status) {
                return $kycDocument;
            }
        }

        return null;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function getUploadedAt(): ?\DateTime
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(?\DateTime $uploadedAt): self
    {
        $this->uploadedAt = $uploadedAt;
        return $this;
    }

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expirationDate;
    }

    public function setExpirationDate(?\DateTimeInterface $expirationDate): self
    {
        $this->expirationDate = $expirationDate;
        return $this;
    }

    public function isExpired(): bool
    {
        if (!$this->expirationDate) {
            return false;
        }
        $today = new \DateTime('today');
        return $today > $this->expirationDate;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getSignedAt(): ?\DateTimeInterface
    {
        return $this->signedAt;
    }

    public function setSignedAt(?\DateTimeInterface $signedAt): self
    {
        $this->signedAt = $signedAt;
        return $this;
    }
}
