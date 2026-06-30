<?php

namespace App\Entity\User;

use App\Entity\User\User\WantFields;
use App\Repository\User\WantRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Table(name: 'user_want')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: WantRepository::class)]

class Want
{
    use WantFields;

    const PIPELINE_INFOPACK_STAGE_ID = 50;
    const PIPELINE_BLOGDOC_STAGE_ID = 200;

    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private $updatedAt;

    #[ORM\Column(name: 'want_nl', type: 'boolean', nullable: true)]
    private $wantNl;

    #[ORM\Column(name: 'want_infopack', type: 'boolean', nullable: true)]
    private $wantInfopack;

    #[ORM\Column(name: 'scpi_infopack', type: 'boolean', nullable: true)]
    private $scpiInfopack;

    #[ORM\Column(name: 'vefa_infopack', type: 'boolean', nullable: true)]
    private $vefaInfopack;

    #[ORM\Column(name: 'contact', type: 'boolean', nullable: true)]
    private $contact;

    #[ORM\Column(name: 'want_synthetic_file', type: 'text', nullable: true)]
    private $wantSyntheticFile;

    #[ORM\Column(name: 'want_doc_blog', type: 'boolean', nullable: true)]
    private $wantDocBlog;

    #[ORM\Column(name: 'ip', type: 'string', length: 100, nullable: true)]
    private $ip;

    #[ORM\OneToOne(targetEntity: 'User', inversedBy: 'wants', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private $user;

    #[ORM\Column(name: 'last_name', type: 'string', length: 45, nullable: true)]
    private $lastName;

    #[ORM\Column(name: 'first_name', type: 'string', length: 45, nullable: true)]
    private $firstName;

    #[ORM\Column(name: 'email', type: 'string', length: 100, nullable: true)]
    private $email;

    #[ORM\Column(name: 'phone', type: 'string', length: 50, nullable: true)]
    private $phone;

    public function __toString(): string
    {

        return $this->getFirstName() . ' ' . $this->getLastName();
    }

    /**
     * @ORM\PreRemove()
     */
    public function preRemove(): void
    {
        $this->getUser();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setWantNl(bool $wantNl): Want
    {
        $this->wantNl = $wantNl;

        return $this;
    }

    public function getWantNl(): ?bool
    {
        return $this->wantNl;
    }

    public function setWantInfopack(bool $wantInfopack): Want
    {
        $this->wantInfopack = $wantInfopack;

        return $this;
    }

    public function getWantInfopack(): ?bool
    {
        return $this->wantInfopack;
    }

    public function setScpiInfopack(bool $scpiInfopack): Want
    {
        $this->scpiInfopack = $scpiInfopack;

        return $this;
    }

    public function getScpiInfopack(): ?bool
    {
        return $this->scpiInfopack;
    }

    public function setVefaInfopack(bool $vefaInfopack): Want
    {
        $this->vefaInfopack = $vefaInfopack;

        return $this;
    }

    public function getVefaInfopack(): ?bool
    {
        return $this->vefaInfopack;
    }

    public function setContact(?bool $contact): Want
    {
        $this->contact = $contact;

        return $this;
    }

    public function getContact(): ?bool
    {
        return $this->contact;
    }

    public function setIp(string $ip): Want
    {
        $this->ip = $ip;

        return $this;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setUser(User $user = null): Want
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setLastName(string $lastName): Want
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getLastName(): ?string
    {
        if(!empty($this->getUser()))
        {
            $this->lastName = $this->getUser()->getLastName();
        }

        return $this->lastName;
    }

    public function setFirstName(string $firstName): Want
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getFirstName(): ?string
    {
        if(!empty($this->getUser()))
        {
            $this->firstName = $this->getUser()->getFirstName();
        }

        return $this->firstName;
    }

    public function setEmail(string $email): Want
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): ?string
    {
        if(!empty($this->getUser()))
        {
            $this->email = $this->getUser()->getEmail();
        }

        return $this->email;
    }

    public function setWantSyntheticFile(string $wantSyntheticFile): Want
    {
        $this->wantSyntheticFile = $wantSyntheticFile;

        return $this;
    }

    public function getWantSyntheticFile(): ?string
    {
        return $this->wantSyntheticFile;
    }

    public function setPhone(?string $phone): Want
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setWantDocBlog(bool $wantDocBlog): Want
    {
        $this->wantDocBlog = $wantDocBlog;

        return $this;
    }

    public function getWantDocBlog(): ?bool
    {
        return $this->wantDocBlog;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): Want
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): Want
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updatedTimestamps()
    {
        $this->setUpdatedAt(new \DateTime('now'));

        if ($this->getCreatedAt() == null) {
            $this->setCreatedAt(new \DateTime('now'));
        }
    }
}
