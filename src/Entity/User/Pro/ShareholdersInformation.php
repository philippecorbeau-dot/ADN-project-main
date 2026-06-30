<?php

namespace App\Entity\User\Pro;

use App\Entity\User\Pro;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'user_pro_shareholders')]
#[ORM\HasLifecycleCallbacks]
class ShareholdersInformation
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\Column(name: 'first_name', type: 'string', length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(name: 'last_name', type: 'string', length: 255)]
    private ?string $lastName = null;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre adresse', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'address_line1', type: 'string', length: 255, nullable: true)]
    private ?string $addressLine1 = null;

    #[ORM\Column(name: 'address_line2', type: 'string', length: 255, nullable: true)]
    private ?string $addressLine2 = null;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre ville', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'city', type: 'string', length: 155, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(name: 'region', type: 'string', length: 155, nullable: true)]
    private ?string $region = null;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre Code postal', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'postal_code', type: 'string', length: 10, nullable: true)]
    private ?string $postalCode = null;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre Pays', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'country', type: 'string', length: 2, nullable: true)]
    private ?string $country = null;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre nationalité', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'nationality', type: 'string', nullable: true)]
    private ?string $nationality = null;

    #[Assert\Date]
    #[Assert\NotBlank(message: 'Merci d\'indiquer votre date d\'anniversaire', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'birthday', type: 'date', nullable: true)]
    private ?\DateTime $birthday = null;

    #[ORM\Column(name: 'birthplace', type: 'string', nullable: true)]
    private ?string $birthplace = null;

    #[ORM\Column(name: 'birth_department', type: 'string', length: 10, nullable: true)]
    private ?string $birthDepartment = null;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Pro::class, inversedBy: 'shareholdersInformations')]
    private $pro;

    #[ORM\ManyToOne(targetEntity: UboDeclaration::class, cascade: ['persist'], inversedBy: 'ubos')]
    #[ORM\JoinColumn(name: 'ubo_declaration_id', referencedColumnName: 'id')]
    private $uboDeclaration;


    public function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getAddressLine1(): ?string
    {
        return $this->addressLine1;
    }

    public function setAddressLine1(?string $addressLine1): self
    {
        $this->addressLine1 = $addressLine1;

        return $this;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function setAddressLine2(?string $addressLine2): self
    {
        $this->addressLine2 = $addressLine2;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): self
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getNationality(): ?string
    {
        return $this->nationality;
    }

    public function setNationality(?string $nationality): self
    {
        $this->nationality = $nationality;

        return $this;
    }

    public function getBirthday(): ?\DateTime
    {
        return $this->birthday;
    }

    public function setBirthday($birthday): self
    {
        $this->birthday = $birthday;

        return $this;
    }

    public function getBirthplace(): ?string
    {
        return $this->birthplace;
    }

    public function setBirthplace(?string $birthplace): self
    {
        
        $this->birthplace = $birthplace;

        return $this;
    }

    public function getBirthDepartment(): ?string
    {
        return $this->birthDepartment;
    }

    public function setBirthDepartment(?string $birthDepartment): self
    {
        $this->birthDepartment = $birthDepartment;

        return $this;
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

    public function getPro()
    {
        return $this->pro;
    }

    public function setPro(Pro $pro)
    {
        $this->pro = $pro;
        return $this;
    }

    public function getUboDeclaration(): ?UboDeclaration
    {
        return $this->uboDeclaration;
    }

    public function setUboDeclaration(?UboDeclaration $uboDeclaration): self
    {
        $this->uboDeclaration = $uboDeclaration;

        return $this;
    }
}
