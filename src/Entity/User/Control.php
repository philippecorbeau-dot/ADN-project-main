<?php

namespace App\Entity\User;

use App\Entity\User\User\ControlFields;
use App\Repository\User\ControlRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: ControlRepository::class)]
#[ORM\Table(name: 'user_control')]
#[UniqueEntity('user')]
#[ORM\HasLifecycleCallbacks]

class Control
{
    use ControlFields;

    const TYPE_AWAITING_REVIEW = 'awaiting_review';
    const TYPE_BLACK_LIST = 'black_list';
    const TYPE_WHITE_LIST = 'white_list';
    const TYPE_POLITICAL_LIST = 'political_list';
    const TYPE_IMPORT = 'import';
    const TYPE_AUTO = 'auto';

    const TYPE_LIST = [
        self::TYPE_AWAITING_REVIEW => 'En attente de vérification',
        self::TYPE_BLACK_LIST => 'Black list',
        self::TYPE_WHITE_LIST => 'White list',
        self::TYPE_POLITICAL_LIST => 'Homme politique',
        self::TYPE_IMPORT => 'Import',
        self::TYPE_AUTO => 'Automatique',
    ];

    const TYPE_LIST_COLOR = [
        self::TYPE_BLACK_LIST => 'orange',
        self::TYPE_WHITE_LIST => 'teal',
        self::TYPE_POLITICAL_LIST => 'purple',
        self::TYPE_AWAITING_REVIEW => 'gray',
        self::TYPE_IMPORT => 'green',
        self::TYPE_AUTO => 'pink',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: true)]
    private $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private $updatedAt;

    #[ORM\Column(name: 'type', type: 'array', nullable: true)]
    private $type;

    #[ORM\Column(name: 'email', type: 'string', length: 128, nullable: true)]
    private $email;

    #[ORM\Column(name: 'last_name', type: 'string', length: 128, nullable: true)]
    private $lastName;

    #[ORM\Column(name: 'first_name', type: 'string', length: 128, nullable: true)]
    private $firstName;

    #[ORM\Column(name: 'company', type: 'string', length: 255, nullable: true)]
    private $company;

    #[ORM\Column(name: 'origin', type: 'string', length: 511, nullable: true)]
    private $origin;

    #[ORM\Column(name: 'comment', type: 'text', nullable: true)]
    private $comment;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'control')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    private $user;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'create_user_id', referencedColumnName: 'id')]
    private $createUser;

    public function __toString()
    {
        return 'E-mail: ' . $this->email;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany($company): self
    {
        $this->company = $company;

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(?string $origin): self
    {
        $this->origin = $origin;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCreateUser(): ?User
    {
        return $this->createUser;
    }

    public function setCreateUser(?User $createUser): self
    {
        $this->createUser = $createUser;

        return $this;
    }

    public function getType(): ?array
    {
        return $this->type;
    }

    public function setType(?array $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTypeList(): array
    {
        return self::TYPE_LIST;
    }

    public function getTypeListLabel(string $type): string
    {
        return self::TYPE_LIST[$type];
    }

    public function getTypeListColor(string $type): string
    {
        return self::TYPE_LIST_COLOR[$type];
    }

    /**
     * @ORM\PrePersist
     */
    public function onCreate()
    {
        $this->setCreatedAt(new \DateTime('now'));
        $this->setUpdatedAt(new \DateTime('now'));
    }

    /**
     * @ORM\PreUpdate
     */
    public function onUpdate()
    {
        $this->setUpdatedAt(new \DateTime('now'));
    }
}
