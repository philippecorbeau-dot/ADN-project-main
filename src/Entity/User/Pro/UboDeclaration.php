<?php

namespace App\Entity\User\Pro;

use App\Repository\User\Pro\UboDeclarationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\User\Pro;

#[ORM\Entity(repositoryClass: UboDeclarationRepository::class)]
#[ORM\Table(name: 'user_pro_ubo_declaration')]
#[ORM\HasLifecycleCallbacks]

class UboDeclaration
{
    use UboDeclarationFields;

    const STATUS_CREATED            = 'CREATED';
    const STATUS_VALIDATION_ASKED   = 'VALIDATION_ASKED';
    const STATUS_VALIDATED          = 'VALIDATED';
    const STATUS_INCOMPLETE         = 'INCOMPLETE';
    const STATUS_REFUSED            = 'REFUSED';

    protected $statusList = [
        self::STATUS_CREATED            => 'Créé',
        self::STATUS_VALIDATION_ASKED   => 'En cours',
        self::STATUS_VALIDATED          => 'Validé',
        self::STATUS_INCOMPLETE         => 'Incomplet',
        self::STATUS_REFUSED            => 'Refusé',
    ];


    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\Column(name: 'status', type: 'string', length: 127, nullable: true)]
    private $status;

    #[ORM\Column(name: 'message', type: 'string', length: 511, nullable: true)]
    private $message;

    #[ORM\ManyToOne(targetEntity: Pro::class, cascade: ['persist'], inversedBy: 'uboDeclarations')]
    #[ORM\JoinColumn(name: 'pro_id', referencedColumnName: 'id')]
    private $pro;

    #[ORM\OneToMany(targetEntity: ShareholdersInformation::class, mappedBy: 'uboDeclaration', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private $ubos;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private $updatedAt;


    public function __construct()
    {
        $this->ubos = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatusList(): array
    {
        return $this->statusList;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getMessageLabel(): string
    {
        return $this->statusList[$this->status];
    }

    public function getPro(): ?Pro
    {
        return $this->pro;
    }

    public function setPro(Pro $pro): self
    {
        $this->pro = $pro;

        return $this;
    }

    public function getUbos(): Collection
    {
        return $this->ubos;
    }

    public function addUbo(ShareholdersInformation $ubo): self
    {
        if (!$this->ubos->contains($ubo)) {
            $this->ubos[] = $ubo;
            $ubo->setUboDeclaration($this);
        }

        return $this;
    }

    public function removeUbo(ShareholdersInformation $ubo): self
    {
        if ($this->ubos->contains($ubo)) {
            $this->ubos->removeElement($ubo);
            if ($ubo->getUboDeclaration() === $this) {
                $ubo->setUboDeclaration(null);
            }
        }

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
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

    public function statusIsIncomplete()
    {
        return $this->status === self::STATUS_INCOMPLETE;
    }

    public function statusIsRefused()
    {
        return $this->status === self::STATUS_REFUSED;
    }

    /**
     * @ORM\PrePersist
     */
    public function onCreate(): void
    {
        $this->setCreatedAt(new \DateTime('now'));
        $this->setUpdatedAt(new \DateTime('now'));
    }

    /**
     * @ORM\PreUpdate
     */
    public function onUpdate(): void
    {
        $this->setUpdatedAt(new \DateTime('now'));
    }
}
