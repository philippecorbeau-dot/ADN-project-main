<?php

namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Table(name: 'user_cgp')]
#[ORM\Entity()]
#[ORM\HasLifecycleCallbacks]

class Cgp
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\Column(name: 'companyName', type: 'string', length: 255)]
    private $companyName;

    #[ORM\Column(name: 'siren', type: 'string', length: 255)]
    private $siren;

    #[ORM\Column(name: 'orias', type: 'string', length: 511)]
    private $orias;

    #[ORM\Column(name: 'function', type: 'string', length: 511)]
    private $function;

    #[ORM\Column(name: 'legal_representative', type: 'string', length: 511)]
    private $legalRepresentative;

    #[ORM\Column(name: 'socialObject', type: 'text', length: 255)]
    private $socialObject;


    #[ORM\Column(name: 'turnover', type: 'decimal')]
    private $turnover;

    #[ORM\Column(name: 'oldResult', type: 'decimal')]
    private $oldResult;

    #[Assert\NotNull(message: 'Ce champ est obligatoire')]
    #[ORM\Column(name: 'forecastTurnover', type: 'decimal')]
    private $forecastTurnover;

    #[Assert\NotNull(message: 'Ce champ est obligatoire')]
    #[ORM\Column(name: 'capital', type: 'decimal')]
    private $capital;

    #[Assert\NotNull(message: 'Ce champ est obligatoire')]
    #[ORM\Column(name: 'stocks', type: 'decimal')]
    private $stocks;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre adresse', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'address_line1', type: 'string', length: 255, nullable: true)]
    private $addressLine1;

    #[ORM\Column(name: 'address_line2', type: 'string', length: 255, nullable: true)]
    private $addressLine2;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre ville', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'city', type:'string', length: 155, nullable: true)]
    private $city;

    #[ORM\Column(name: 'region', type: 'string', length: 155, nullable: true)]
    private $region;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre Code postal', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'postal_code', type: 'string', length: 10)]
    private $postalCode;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre Pays', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'country', type: 'string', length: 2)]
    private $country;

    #[ORM\OneToOne(targetEntity: 'User', inversedBy: 'cgp')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    private $user;

    public function getId(): int
    {
        return $this->id;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): self
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(?string $siren): self
    {
        $this->siren = $siren;

        return $this;
    }

    public function getOrias(): ?string
    {
        return $this->orias;
    }

    public function setOrias(?string $orias): self
    {
        $this->orias = $orias;

        return $this;
    }

    public function getFunction(): ?string
    {
        return $this->function;
    }

    public function setFunction(?string $function): self
    {
        $this->function = $function;

        return $this;
    }

    public function getLegalRepresentative(): ?string
    {
        return $this->legalRepresentative;
    }

    public function setLegalRepresentative(?string $legalRepresentative): self
    {
        $this->legalRepresentative = $legalRepresentative;

        return $this;
    }

    public function getSocialObject(): ?string
    {
        return $this->socialObject;
    }

    public function setSocialObject(?string $socialObject): self
    {
        $this->socialObject = $socialObject;

        return $this;
    }
 
    public function getTurnover(): ?int
    {
        return $this->turnover;
    }

    public function setTurnover(?int $turnover): self
    {
        $this->turnover = $turnover;

        return $this;
    }

    public function getOldResult(): ?int
    {
        return $this->oldResult;
    }

    public function setOldResult(?int $oldResult): self
    {
        $this->oldResult = $oldResult;

        return $this;
    }

    public function getForecastTurnover(): ?int
    {
        return $this->forecastTurnover;
    }

    public function setForecastTurnover(?int $forecastTurnover): self
    {
        $this->forecastTurnover = $forecastTurnover;

        return $this;
    }

    public function getCapital(): ?int
    {
        return $this->capital;
    }

    public function setCapital(?int $capital): self
    {
        $this->capital = $capital;

        return $this;
    }

    public function getStocks(): ?string
    {
        return $this->stocks;
    }

    public function setStocks(?string $stocks): self
    {
        $this->stocks = $stocks;

        return $this;
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

    public function getPostalCode(): ?int
    {
        return $this->postalCode;
    }

    public function setPostalCode(?int $postalCode): self
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

    public function getUser()
    {
        return $this->user;
    }

    public function setUser($user): self
    {
        $this->user = $user;

        return $this;
    }
}
