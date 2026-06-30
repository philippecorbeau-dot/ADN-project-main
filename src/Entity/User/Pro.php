<?php

namespace App\Entity\User;

use App\Entity\User\Pro\ShareholdersInformation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\User\Pro\UboDeclaration;

#[ORM\Entity]
#[ORM\Table(name: 'user_pro')]
#[ORM\HasLifecycleCallbacks]

class Pro
{
    const SOCIAL_FORM_EI = 'EI';
    const SOCIAL_FORM_EURL = 'EURL';
    const SOCIAL_FORM_SARL = 'SARL';
    const SOCIAL_FORM_SASU = 'SASU';
    const SOCIAL_FORM_SAS = 'SAS';
    const SOCIAL_FORM_SA = 'SA';
    const SOCIAL_FORM_SNC = 'SNC';
    const SOCIAL_FORM_SCS = 'SCS';
    const SOCIAL_FORM_SCA = 'SCA';

    const SOCIAL_FORM_LIST = [
        self::SOCIAL_FORM_EI => 'Entrepreneur individuel',
        self::SOCIAL_FORM_EURL => 'Entreprise unipersonnelle à responsabilité limitée',
        self::SOCIAL_FORM_SARL => 'Société à responsabilité limitée',
        self::SOCIAL_FORM_SASU => 'Société par actions simplifiée unipersonnelle',
        self::SOCIAL_FORM_SAS => 'Société par actions simplifiée',
        self::SOCIAL_FORM_SA => 'Société anonyme',
        self::SOCIAL_FORM_SNC => 'Société en nom collectif',
        self::SOCIAL_FORM_SCS => 'Société en commandite simple',
        self::SOCIAL_FORM_SCA => 'Société en commandite par actions',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre Dénomination sociale', groups: ['pro'])]
    #[ORM\Column(name: 'companyName', type: 'string', length: 255)]
    private $companyName;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre siren', groups: ['pro'])]
    #[ORM\Column(name: 'siren', type: 'string', length: 255)]
    private $siren;

    #[ORM\Column(name: 'head_office', type: 'string', length: 255, nullable: true)]
    private $headOffice;

    #[Assert\NotBlank(message: 'Merci de renseigner l\'Objet social synthétisé', groups: ['pro'])]
    #[ORM\Column(name: 'socialObject', type: 'text')]
    private $socialObject;

    #[ORM\Column(name: 'legalRepresentative', type: 'string', length: 255, nullable: true)]
    private $legalRepresentative;

    #[ORM\Column(name: 'legal_representative_firstname', type: 'string', length: 255)]
    private $legalRepresentativeFirstname;

    #[ORM\Column(name: 'legal_representative_lastname', type: 'string', length: 255)]
    private $legalRepresentativeLastname;

    #[ORM\Column(name: 'shareholders', type: 'string', nullable: true)]
    private $shareholders;

    #[ORM\Column(name: 'turnover', type: 'decimal', nullable: true)]
    private $turnover;

    #[ORM\Column(name: 'oldResult', type: 'decimal', nullable: true)]
    private $oldResult;

    #[ORM\Column(name: 'yearResult', type: 'date', nullable: true)]
    private $yearResult;

    #[ORM\Column(name: 'forecastTurnover', type: 'decimal', nullable: true)]
    private $forecastTurnover;

    #[ORM\Column(name: 'year_forecast_turnover', type: 'date', nullable: true)]
    private $yearForecastTurnover;

    #[ORM\Column(name: 'capital', type: 'decimal', nullable: true)]
    private $capital;

    #[ORM\Column(name: 'stocks', type: 'decimal', nullable: true)]
    private $stocks;

    #[ORM\Column(name: 'address_line1', type: 'string', length: 255, nullable: true)]
    private $addressLine1;

    #[ORM\Column(name: 'address_line2', type: 'string', length: 255, nullable: true)]
    private $addressLine2;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre ville', groups: ['profile', 'invest', 'pro'])]
    #[ORM\Column(name: 'city', type: 'string', length: 155, nullable: true)]
    private $city;

    #[Assert\NotBlank(message: 'Merci de renseigner la région', groups: ['pro'])]
    #[ORM\Column(name: 'region', type: 'string', length: 155, nullable: true)]
    private $region;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre Code postal', groups: ['profile', 'invest', 'pro'])]
    #[ORM\Column(name: 'postal_code', type: 'string', length: 10)]
    private $postalCode;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre Pays', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'country', type: 'string', length: 2)]
    private $country;

    #[ORM\OneToMany(targetEntity: UboDeclaration::class, mappedBy: 'pro', cascade: ['persist'])]
    private $uboDeclarations;

    #[ORM\OneToMany(targetEntity: ShareholdersInformation::class, mappedBy: 'pro', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private $shareholdersInformations;

    #[ORM\OneToOne(targetEntity: User::class, mappedBy: 'pro')]
    private $user;

    #[ORM\Column(type: 'string', nullable: true)]
    private $socialForm;

    #[ORM\Column(name: 'awareness_balance_sheet', type: 'boolean', nullable: true)]
    private $awarenessBalanceSheet;

    #[ORM\Column(name: 'awareness_turnover', type: 'boolean', nullable: true)]
    private $awarenessTurnover;

    #[ORM\Column(name: 'awareness_equity', type: 'boolean', nullable: true)]
    private $awarenessEquity;

    #[ORM\Column(name: 'attest_balance_sheet', type: 'boolean', nullable: true)]
    private $attestBalanceSheet;

    #[ORM\Column(name: 'attest_turnover', type: 'boolean', nullable: true)]
    private $attestTurnover;

    #[ORM\Column(name: 'attest_equity', type: 'boolean', nullable: true)]
    private $attestEquity;

    #[ORM\Column(name: 'attest_aware', type: 'boolean', nullable: true)]
    private $attestAware;

    #[ORM\Column(name: 'attest_truth', type: 'boolean', nullable: true)]
    private $attestTruth;


    public function __construct()
    {
        $date = new \DateTime();
        $this->setYearForecastTurnover($date);

        $date->setTimestamp(strtotime('-1 Year'));
        $this->setYearResult($date);
        $this->shareholdersInformations = new ArrayCollection();
        $this->uboDeclarations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setCompanyName(string $companyName): Pro
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setSiren(string $siren): Pro
    {
        $this->siren = $siren;

        return $this;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSocialObject(string $socialObject): Pro
    {
        $this->socialObject = $socialObject;

        return $this;
    }

    public function getSocialObject(): ?string
    {
        return $this->socialObject;
    }

    public function setLegalRepresentative(string $legalRepresentative): Pro
    {
        $this->legalRepresentative = $legalRepresentative;

        return $this;
    }

    public function getLegalRepresentative(): ?string
    {
        return $this->legalRepresentative;
    }

    public function setShareholders(?string $shareholders): Pro
    {
        $this->shareholders = $shareholders;

        return $this;
    }

    public function getShareholders(): ?string
    {
        return $this->shareholders;
    }

    public function setTurnover(int $turnover): Pro
    {
        $this->turnover = $turnover;

        return $this;
    }

    public function getTurnover(): ?int
    {
        return $this->turnover;
    }

    public function setOldResult(int $oldResult): Pro
    {
        $this->oldResult = $oldResult;

        return $this;
    }

    public function getOldResult(): ?int
    {
        return $this->oldResult;
    }

    public function setYearResult(\DateTime $yearResult): Pro
    {
        $this->yearResult = $yearResult;

        return $this;
    }

    public function getYearResult(): ?\DateTime
    {
        return $this->yearResult;
    }

    public function setForecastTurnover(int $forecastTurnover): Pro
    {
        $this->forecastTurnover = $forecastTurnover;

        return $this;
    }

    public function getForecastTurnover(): ?int
    {
        return $this->forecastTurnover;
    }

    public function setCapital(int $capital): Pro
    {
        $this->capital = $capital;

        return $this;
    }

    public function getCapital(): ?int
    {
        return $this->capital;
    }

    public function setStocks(int $stocks): Pro
    {
        $this->stocks = $stocks;

        return $this;
    }

    public function getStocks(): ?int
    {
        return $this->stocks;
    }

    public function setHeadOffice(string $headOffice): Pro
    {
        $this->headOffice = $headOffice;

        return $this;
    }

    public function getHeadOffice(): ?string
    {
        return $this->headOffice;
    }

    public function setYearForecastTurnover(\DateTime $yearForecastTurnover): Pro
    {
        $this->yearForecastTurnover = $yearForecastTurnover;

        return $this;
    }

    public function getYearForecastTurnover(): \DateTime
    {
        return $this->yearForecastTurnover;
    }

    public function setAddressLine1(?string $addressLine1): Pro
    {
        $this->addressLine1 = $addressLine1;

        return $this;
    }

    public function getAddressLine1(): ?string
    {
        return $this->addressLine1;
    }

    public function setAddressLine2(?string $addressLine2): Pro
    {
        $this->addressLine2 = $addressLine2;

        return $this;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function setCity(string $city): Pro
    {
        $this->city = $city;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setRegion(?string $region): Pro
    {
        $this->region = $region;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setPostalCode(string $postalCode): Pro
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setCountry(string $country): Pro
    {
        $this->country = $country;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function getUboDeclarations()
    {
        return $this->uboDeclarations;
    }

    public function getUboDeclaration(): ?UboDeclaration
    {
        $lastUboDeclaration = $this->uboDeclarations->last();
        return $lastUboDeclaration !== false ? $lastUboDeclaration : null;
    }

    public function addUboDeclaration(UboDeclaration $uboDeclaration): self
    {
        if (!$this->uboDeclarations->contains($uboDeclaration)) {
            $this->uboDeclarations[] = $uboDeclaration;
            $uboDeclaration->setPro($this);
        }

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getSocialForm(): ?string
    {
        return $this->socialForm;
    }

    public function setSocialForm(?string $socialForm): self
    {
        $this->socialForm = $socialForm;

        return $this;
    }

    public function getFormList(): array
    {
       return self::SOCIAL_FORM_LIST;
    }

    public function getLegalRepresentativeFirstname()
    {
        return $this->legalRepresentativeFirstname;
    }

    public function setLegalRepresentativeFirstname($legalRepresentativeFirstname): Pro
    {
        $this->legalRepresentativeFirstname = $legalRepresentativeFirstname;
        return $this;
    }

    public function getLegalRepresentativeLastname()
    {
        return $this->legalRepresentativeLastname;
    }

    public function setLegalRepresentativeLastname($legalRepresentativeLastname): Pro
    {
        $this->legalRepresentativeLastname = $legalRepresentativeLastname;
        return $this;
    }

    public function isAwarenessBalanceSheet()
    {
        return $this->awarenessBalanceSheet;
    }

    public function setAwarenessBalanceSheet($awarenessBalanceSheet): Pro
    {
        $this->awarenessBalanceSheet = $awarenessBalanceSheet;
        return $this;
    }

    public function isAwarenessTurnover()
    {
        return $this->awarenessTurnover;
    }

    public function setAwarenessTurnover($awarenessTurnover): Pro
    {
        $this->awarenessTurnover = $awarenessTurnover;
        return $this;
    }

    public function isAwarenessEquity()
    {
        return $this->awarenessEquity;
    }

    public function setAwarenessEquity($awarenessEquity): Pro
    {
        $this->awarenessEquity = $awarenessEquity;
        return $this;
    }
    
    public function getShareholdersInformations()
    {
        return $this->shareholdersInformations;
    }

    public function addShareholdersInformation($shareholdersInformations)
    {
        $shareholdersInformations->setPro($this);
        $this->shareholdersInformations->add($shareholdersInformations);
    }
    
    public function removeShareholdersInformation($shareholdersInformations)
    {
        $this->shareholdersInformations->removeElement($shareholdersInformations);
    }

    public function isAttestBalanceSheet()
    {
        return $this->attestBalanceSheet;
    }

    public function setAttestBalanceSheet($attestBalanceSheet): Pro
    {
        $this->attestBalanceSheet = $attestBalanceSheet;
        return $this;
    }

    public function isAttestAware()
    {
        return $this->attestAware;
    }

    public function setAttestAware($attestAware): Pro
    {
        $this->attestAware = $attestAware;
        return $this;
    }

    public function isAttestTruth()
    {
        return $this->attestTruth;
    }

    public function setAttestTruth($attestTruth): Pro
    {
        $this->attestTruth = $attestTruth;
        return $this;
    }

    public function getAttestTurnover()
    {
        return $this->attestTurnover;
    }

    public function setAttestTurnover($attestTurnover): Pro
    {
        $this->attestTurnover = $attestTurnover;
        return $this;
    }

    public function getAttestEquity()
    {
        return $this->attestEquity;
    }

    public function setAttestEquity($attestEquity): Pro
    {
        $this->attestEquity = $attestEquity;
        return $this;
    }

    public function getPhone()
    {
        if (empty($this->user)) {
            return null;
        }

        return $this->getUser()->getPhone();
    }

    public function setPhone($phone): Pro
    {
        $this->getUser()->setPhone($phone);
        return $this;
    }
}
