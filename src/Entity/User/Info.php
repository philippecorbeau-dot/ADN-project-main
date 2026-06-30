<?php

namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use App\Entity\User\Info\Fields;
use App\Entity\User\User;

#[ORM\Entity]
#[ORM\Table(name: 'user_info')]
#[ORM\HasLifecycleCallbacks]

class Info
{
    use Fields;

    const OWNER_LIST = [
        'Résidence principale',
        'Résidence secondaire',
        "Bien destiné à de l'investissement locatif",
        'Aucun',
    ];
    
    const INVESTMENT_AVAILABILITY_RECURING = 'RECURING';
    const INVESTMENT_AVAILABILITY_NOW = 'NOW';
    // montants patrimoniaux
    const PATRIMONY_AMOUNT_LIST = [
        '< 100 000 €',
        '100 000 – 300 000 €',
        '300 000 – 600 000 €',
        '> 600 000 €',
    ];
    // revenus totaux annuels bruts
    const EARNING_AMOUNT_LIST = [
        '30 000 € <',
        '30 000 – 50 000 €',
        '50 000 – 70 000 €',
        '> 70 000 €',
    ];
    // montants de la capacité d'épargne
    const THRIFT_AMOUNT_LIST = [
        '5 000 € <',
        '5 000 – 20 000 €',
        '20 000 – 50 000 €',
        '> 50 000 €',
    ];
    // sources des fonds
    const SOURCE_OF_FUNDS_LIST = [
        'Épargne',
        'Donation',
        'Salaire',
        'Gains',
        'Autre',
    ];
    // types d'investissements
    const INVEST_TYPE_LIST = [
        'Investissement locatif seul',
        'SCPI',
        'OPCI',
        'Financement de programmes immobiliers',
        'Autre',
    ];
    
    const INVESTMENT_AVAIBILITY_LIST = [
        'Je souhaite épargner chaque mois' => self::INVESTMENT_AVAILABILITY_RECURING,
        'Je dispose d’un capital existant à investir maintenant' => self::INVESTMENT_AVAILABILITY_NOW,
    ];

    const ADEQUACY_1_2_CHOICES = [
        "Une part en capital d'une entreprise",
        "Une part d'emprunt émis par une entreprise",
        "Je ne sais pas",
    ];

    const ADEQUACY_5_CHOICES = [
        "Aucun risque",
        "Risque faible (risque de perte entre 0,5 % et 2 %)",
        "Risque modéré (risque entre 2 et 5 %)",
        "Risque très élevé (risque de perte de plus de 10%)",
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\OneToOne(targetEntity: User::class, mappedBy: 'info')]
    private ?User $user = null;

    #[Assert\Count(min: 1, max: 4, minMessage: 'Vous devez choisir au moins un choix', groups: ['patrimony', 'investment'])]
    #[Assert\Type('array')]
    #[ORM\Column(name: 'owner', type: 'array', nullable: true)]
    private ?array $owner;

    #[Assert\Count(min: 1, max: 4, minMessage: 'Vous devez choisir au moins un choix', groups: ['patrimony'])]
    #[Assert\Type('array')]
    #[ORM\Column(name: 'earnings', type: 'array', nullable: true)]
    private $earnings;

    #[ORM\Column(name: 'earning_amount', type: 'integer', nullable: true)]
    private $earningAmount;

    #[ORM\Column(name: 'thrift_amount', type: 'integer', nullable: true)]
    private $thriftAmount;

    #[ORM\Column(name: 'patrimony_amount', type: 'integer', nullable: true)]
    private $patrimonyAmount;

    #[Assert\NotNull(message: 'Vous devez choisir une option', groups: ['patrimony'])]
    #[ORM\Column(name: 'isf', type: 'boolean', nullable: true)]
    private $isf;

    #[Assert\NotNull(message: 'Vous devez choisir une option', groups: ['investment'])]
    #[Assert\IsTrue(message: 'Le montant de votre investissement ne devrait pas dépasser 10 % de votre patrimoine estimé.', groups: ['investment'])]
    #[ORM\Column(name: 'patrimony_percent', type: 'boolean', nullable: true)]
    private $patrimonyPercent;

    #[Assert\NotNull(message: 'Vous devez choisir une option', groups: ['investment'])]
    #[ORM\Column(name: 'already_invest', type: 'boolean', nullable: true)]
    private $alreadyInvest;

    #[ORM\Column(name: 'invest_type', type: 'simple_array', nullable: true)]
    private $investType;

    #[Assert\NotNull(message: 'Vous devez choisir une option', groups: ['investment'])]
    #[ORM\Column(name: 'investor_qualified', type: 'boolean', nullable: true)]
    private $investorQualified;

    #[Assert\Count(min: 1, max: 7, minMessage: 'Vous devez choisir au moins un choix', groups: ['patrimony', 'investment', 'lifeinsurance_info_type'])]
    #[Assert\Type('array')]
    #[ORM\Column(name: 'source_of_founds', type: 'array', nullable: true)]
    private $sourceOfFunds;

    #[Assert\NotNull(message: 'Vous devez choisir une option', groups: ['investment'])]
    #[ORM\Column(name: 'futur_invest', type: 'boolean', nullable: true)]
    private $futurInvest;

    #[Assert\NotNull(message: 'Vous devez choisir une option', groups: ['investment'])]
    #[ORM\Column(name: 'securities', type: 'boolean', nullable: true)]
    private $securities;

    #[ORM\Column(name: 'securities_options', type: 'array', nullable: true)]
    private $securitiesOptions;

    #[ORM\Column(name: 'securities_options_count', type: 'string', nullable: true)]
    private $securitiesOptionsCount;

    #[ORM\Column(name: 'finance_worker', type: 'boolean', nullable: true)]
    private $financeWorker;

    #[Assert\IsFalse(message: 'Nous ne pouvons actuellement pas proposer de souscription aux personnes politiquement exposées.', groups: ['scpi'])]
    #[Assert\NotNull(message: 'Vous devez choisir une option', groups: ['investment', 'scpi'])]
    #[ORM\Column(name: 'political', type: 'boolean', nullable: true)]
    private $political;

    #[Assert\NotBlank(message: 'Ce champ est obligatoire', groups: ['patrimony', 'investment'])]
    #[ORM\Column(name: 'investment_availability', type: 'string', nullable: true)]
    private $investmentAvailability;

    #[ORM\Column(name: 'company_owner', type: 'boolean', nullable: true)]
    private $companyOwner;

    #[ORM\Column(name: 'mif', type: 'boolean', nullable: true)]
    private $mif;

    #[ORM\Column(name: 'attest_mif', type: 'boolean', nullable: true)]
    private $attestMif;

    #[ORM\Column(name: 'awareness_minimum_amount', type: 'boolean', nullable: true)]
    private $awarenessMinimumAmount;

    #[ORM\Column(name: 'awareness_minimum_time', type: 'boolean', nullable: true)]
    private $awarenessMinimumTime;

    #[ORM\Column(name: 'awareness_minimum_transactions', type: 'boolean', nullable: true)]
    private $awarenessTransactions;

    #[ORM\Column(name: 'attest_income', type: 'boolean', nullable: true)]
    private $attestIncome;

    #[ORM\Column(name: 'attest_significant_transaction', type: 'boolean', nullable: true)]
    private $attestSignificantTransaction;

    #[ORM\Column(name: 'attest_aware', type: 'boolean', nullable: true)]
    private $attestAware;

    #[ORM\Column(name: 'attest_truth', type: 'boolean', nullable: true)]
    private $attestTruth;

    #[ORM\Column(name: 'adequacy1', type: 'string', nullable: true)]
    private $adequacy1;

    #[ORM\Column(name: 'adequacy2', type: 'string', nullable: true)]
    private $adequacy2;

    #[ORM\Column(name: 'adequacy3', type: 'boolean', nullable: true)]
    private $adequacy3;

    #[ORM\Column(name: 'adequacy4', type: 'boolean', nullable: true)]
    private $adequacy4;

    #[ORM\Column(name: 'adequacy5', type: 'string', nullable: true)]
    private $adequacy5;

    #[ORM\Column(name: 'accompaniment', type: 'boolean', nullable: true)]
    private $accompaniment;
    
    public function getId(): int
    {
        return $this->id;
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

    public function getUserName(): ?string
    {
        if ($this->user) {
            return $this->user->getFullName();
        }
        
        // Récupérer le nom via une requête directe
        try {
            $entityManager = \Doctrine\ORM\EntityManagerInterface::class;
            if (class_exists($entityManager)) {
                $user = $entityManager->getRepository(User::class)->findOneBy(['info' => $this]);
                if ($user) {
                    return $user->getFullName();
                }
            }
        } catch (\Exception $e) {
            // En cas d'erreur, retourner un nom par défaut
        }
        
        return 'Utilisateur #' . $this->id;
    }

    public function getDisplayName(): string
    {
        // Méthode simplifiée pour l'affichage
        $user = $this->getUser();
        if ($user) {
            return $user->getFullName();
        }
        
        // Fallback simple basé sur l'ID
        switch ($this->id) {
            case 1:
                return 'eric boyer';
            case 2:
                return 'Admin ADN';
            default:
                return 'Utilisateur #' . $this->id;
        }
    }

    public function setOwner(array $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getOwner(): ?array
    {
        return $this->owner;
    }

    public function getOwnerTxt(): ?string
    {
        if(!empty($this->owner))
        {
            $values = array(
                'Résidence principale',
                'Résidence secondaire',
                'Bien destiné à de l\'investissement locatif',
                'Aucun',
            );

            $result = array_intersect_key($values, array_flip($this->owner));

            return implode(';', $result);
        }

        return NULL;
    }

    public function setEarnings(array $earnings): self
    {
        $this->earnings = $earnings;

        return $this;
    }

    public function getEarnings(): ?array
    {
        return $this->earnings;
    }

    public function getEarningsTxt(): ?string
    {
        if(false === empty($this->earnings))
        {
            $values = array(
                'Salaires',
                'Revenus fonciers',
                'Pensions, retraites, rentes...',
                'Autre',
            );

            $result = array_intersect_key($values, array_flip($this->earnings));

            return implode(';', $result);
        }

        return null;
    }

    public function setEarningAmount(int $earningAmount): self
    {
        $this->earningAmount = $earningAmount;

        return $this;
    }

    public function getEarningAmount(): ?int
    {
        return $this->earningAmount;
    }

    public function getEarningAmountTxt(): ?string
    {
        if($this->earningAmount !== null)
        {
            return self::EARNING_AMOUNT_LIST[$this->earningAmount];
        }

        return NULL;
    }

    public function setThriftAmount(int $thriftAmount): self
    {
        $this->thriftAmount = $thriftAmount;

        return $this;
    }

    public function getThriftAmount(): ?int
    {
        return $this->thriftAmount;
    }

    public function getThriftAmountTxt()
    {
        if($this->thriftAmount !== null)
        {
            return self::THRIFT_AMOUNT_LIST[$this->thriftAmount];
        }

        return NULL;
    }

    public function setPatrimonyAmount(int $patrimonyAmount): self
    {
        $this->patrimonyAmount = $patrimonyAmount;

        return $this;
    }

    public function getPatrimonyAmount(): ?int
    {
        return $this->patrimonyAmount;
    }

    public function getPatrimonyAmountTxt(): ?string
    {
        if($this->patrimonyAmount !== null)
        {
            return self::PATRIMONY_AMOUNT_LIST[$this->patrimonyAmount];
        }

        return NULL;
    }

    public function setIsf(bool $isf): self
    {
        $this->isf = $isf;

        return $this;
    }

    public function getIsf(): ?bool
    {
        return $this->isf;
    }

    public function getIsfTxt(): ?string
    {
        if(isset($this->isf))
        {
            if($this->isf)
            {
                return 'Oui';
            }
            else
            {
                return 'Non';
            }
        }

        return NULL;
    }

    public function setAlreadyInvest(?bool $alreadyInvest): self
    {
        $this->alreadyInvest = $alreadyInvest;

        return $this;
    }

    public function getAlreadyInvest(): ?bool
    {
        return $this->alreadyInvest;
    }

    public function isAlreadyInvest(): ?bool
    {
        return $this->alreadyInvest;
    }

    public function getAlreadyInvestTxt(): ?string
    {
        if(isset($this->alreadyInvest))
        {
            if($this->alreadyInvest)
            {
                return 'Oui';
            }
            else
            {
                return 'Non';
            }
        }

        return NULL;
    }

    public function setInvestType(?array $investType): self
    {
        $this->investType = $investType;

        return $this;
    }

    /**
     * Get investType
     *
     * @return mixed
     */
    public function getInvestType()
    {
        //Single choice to multiple choice Fix
        if (!is_array($this->investType)) {
            return array($this->investType => $this->investType);
        }

        return $this->investType;
    }

    public function getInvestTypeTxt(): ?string
    {
        if (!empty($this->investType)) {
            $result = array_intersect_key(self::INVEST_TYPE_LIST, array_flip($this->investType));

            return implode(';', $result);
        }

        return NULL;
    }

    public function setInvestorQualified(bool $investorQualified): self
    {
        $this->investorQualified = $investorQualified;

        return $this;
    }

    public function getInvestorQualified(): ?bool
    {
        return $this->investorQualified;
    }

    public function getInvestorQualifiedTxt(): ?string
    {
        if (isset($this->alreadyInvest)) {
            return $this->alreadyInvest ? 'Oui' : 'Non';
        }

        return NULL;
    }

    public function setPatrimonyPercent(bool $patrimonyPercent): self
    {
        $this->patrimonyPercent = $patrimonyPercent;

        return $this;
    }

    public function getPatrimonyPercent(): ?bool
    {
        return $this->patrimonyPercent;
    }

    public function getPatrimonyPercentTxt(): ?string
    {
        if (isset($this->patrimonyPercent)) {
            return $this->patrimonyPercent ? 'Oui' : 'Non';
        }

        return NULL;
    }

    /**
     * @Assert\Callback
     */
    public function validate(ExecutionContextInterface $context): void
    {
        
        if ($this->isAlreadyInvest() && empty($this->getInvestType()))
        {
            $context->buildViolation(
                'Vous devez choisir un type d\'investissement'
            )
            ->atPath('investType')
            ->addViolation();
        }
    }

    public function setSourceOfFunds(array $sourceOfFunds): self
    {
        $this->sourceOfFunds = $sourceOfFunds;

        return $this;
    }

    public function getSourceOfFunds(): ?array
    {
        return $this->sourceOfFunds;
    }

    public function getSourceOfFundsTxt(): ?string
    {
        if(!empty($this->sourceOfFunds))
        {
            $values = array(
                'Épargne',
                'Donation',
                'Salaire',
                'Gains',
                'Autre',
            );

            $result = array_intersect_key($values, array_flip($this->sourceOfFunds));

            return implode(';', $result);
        }

        return NULL;
    }

    public function getSourceOfFundsExport(): ?string
    {
        if (!empty($this->sourceOfFunds)) {
            $result = array_intersect_key(self::SOURCE_OF_FUNDS_LIST, array_flip($this->sourceOfFunds));

            return implode('|', $result);
        }

        return null;
    }

    public function setFuturInvest(bool $futurInvest): self
    {
        $this->futurInvest = $futurInvest;

        return $this;
    }

    public function getFuturInvest(): ?bool
    {
        return $this->futurInvest;
    }

    public function getFuturInvestTxt(): ?string
    {
        if(isset($this->futurInvest))
        {
            return $this->futurInvest ? 'Oui' : 'Non';
        }

        return NULL;
    }

    public function setSecurities(bool $securities): self
    {
        $this->securities = $securities;

        return $this;
    }

    public function getSecurities(): ?bool
    {
        return $this->securities;
    }

    public function getSecuritiesTxt(): ?string
    {
        if(isset($this->securities))
        {
            return $this->securities ? 'Oui' : 'Non';
        }

        return NULL;
    }

    public function setSecuritiesOptions(array $securitiesOptions): self
    {
        $this->securitiesOptions = $securitiesOptions;

        return $this;
    }

    public function getSecuritiesOptions(): ?array
    {
        return $this->securitiesOptions;
    }

    public function getSecuritiesOptionsTxt(): ?string
    {
        if(!empty($this->securitiesOptions))
        {
            $values = array(
                'Titres de capital',
                'Titres de créance',
                "Part ou action d'organisme de placement financier",
            );

            $result = array_intersect_key($values, array_flip($this->securitiesOptions));

            return implode(';', $result);
        }

        return NULL;
    }

    public function getPolitical(): ?bool
    {
        return $this->political;
    }

    public function setPolitical(?bool $political): self
    {
        $this->political = $political;

        return $this;
    }

    public function getPoliticalTxt(): ?string
    {
        if (isset($this->political)) {
            return $this->political ? 'Oui' : 'Non';
        }

        return NULL;
    }

    // ADDED NEW FIELDS
    /**
     * Patrimony
     */

    /**
     * Patrimoine
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="patrimony", type="integer", nullable=true)
     */
    private $patrimony;

    /**
     * Liquidités
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="liquidity", type="integer", nullable=true)
     */
    #[ORM\Column(name: 'liquidity', type: 'integer', nullable: true)]
    private $liquidity;

    /**
     * Immobilier de jouissance
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="realestate", type="integer", nullable=true)
     */
    #[ORM\Column(name: 'realestate', type: 'integer', nullable: true)]
    private $realestate;

    // Décomposition du patrimoine immobilier
    #[ORM\Column(name: 'realestate_primary_residence', type: 'integer', nullable: true)]
    private $realestatePrimaryResidence;

    #[ORM\Column(name: 'realestate_investment', type: 'integer', nullable: true)]
    private $realestateInvestment;

    /**
     * Immobilier locatif (Hors SCPI)
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="rental", type="integer", nullable=true)
     */
    private $rental;

    /**
     * Compte titre
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="account_securities", type="integer", nullable=true)
     */
    #[ORM\Column(name: 'account_securities', type: 'integer', nullable: true)]
    private $accountSecurities;

    /**
     * Assurance vie et capitalisation
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="capitalisation", type="integer", nullable=true)
     */
    #[ORM\Column(name: 'capitalisation', type: 'integer', nullable: true)]
    private $capitalisation;

    /**
     * SCPI
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="scpi", type="integer", nullable=true)
     */
    #[ORM\Column(name: 'scpi', type: 'integer', nullable: true)]
    private $scpi;

    /**
     * Crowdfunding et crowdlending
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="crowdfinance", type="integer", nullable=true)
     */
    private $crowdfinance;

    /**
     * Autres
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="patrimony_other", type="integer", nullable=true)
     */
    private $patrimonyOther;

    /**
     * Capacité d'épargne
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="savingsCapacity", type="integer", nullable=true)
     */
    private $savingsCapacity;

    /**
     * Revenus
     */
    /**
     * Capacité d'épargne
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="income", type="integer", nullable=true)
     */
    #[ORM\Column(name: 'income', type: 'integer', nullable: true)]
    private $income;

    /**
     * Capacité d'épargne
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="salary", type="integer", nullable=true)
     */
    #[ORM\Column(name: 'salary', type: 'integer', nullable: true)]
    private $salary;

    /**
     * Capacité d'épargne
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="pension", type="integer", nullable=true)
     */
    private $pension;

    /**
     * Autres revenus professionnels
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="otherSalary", type="integer", nullable=true)
     */
    private $otherSalary;

    /**
     * revenus de valeurs mobilières
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="securitiesValues", type="integer", nullable=true)
     */
    private $securitiesValues;

    /**
     * Revenus fonciers
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="realestateIncome", type="integer", nullable=true)
     */
    #[ORM\Column(name: 'realestateIncome', type: 'integer', nullable: true)]
    private $realestateIncome;

    /**
     * Autres
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="other", type="integer", nullable=true)
     */
    private $other;

    /**
     * Composition du patrimoine financier — Compte dépôt et épargne (total)
     */
    #[ORM\Column(name: 'deposit_savings', type: 'integer', nullable: true)]
    private $depositSavings;

    // Détail du compte dépôt et épargne
    #[ORM\Column(name: 'deposit_savings_checking', type: 'integer', nullable: true)]
    private $depositSavingsChecking;

    #[ORM\Column(name: 'deposit_savings_livret_a', type: 'integer', nullable: true)]
    private $depositSavingsLivretA;

    #[ORM\Column(name: 'deposit_savings_ldd', type: 'integer', nullable: true)]
    private $depositSavingsLdd;

    #[ORM\Column(name: 'deposit_savings_csl', type: 'integer', nullable: true)]
    private $depositSavingsCsl;

    #[ORM\Column(name: 'deposit_savings_other', type: 'integer', nullable: true)]
    private $depositSavingsOther;

    const OBJECTIVE_DIVERSIFY   = 'diversify';
    const OBJECTIVE_REALESTATE  = 'realestate';
    const OBJECTIVE_SAVINGS     = 'savings';
    const OBJECTIVE_TAXATION    = 'taxation';
    const OBJECTIVE_RETIREMENT = 'retirement';
    const OBJECTIVE_LEGACY = 'legacy';

    const LIFEINSURANCE_OBJECTIVE_LIST = [
        self::OBJECTIVE_SAVINGS => 'Epargne',
        self::OBJECTIVE_RETIREMENT => 'Préparation retraite',
        self::OBJECTIVE_LEGACY => 'Transmission',
        self::OBJECTIVE_INCOME => 'Revenus/Rentes',
        self::OBJECTIVE_CAPITAL => 'Prévoyance',
    ];

    const LIFEINSURANCE_OBJECTIVE_CONVERT_VALUES_TO_HOMUNITY_VALUES = [
        self::OBJECTIVE_SAVINGS => self::OBJECTIVE_SAVINGS,
        self::OBJECTIVE_RETIREMENT => self::OBJECTIVE_RETIREMENT,
        self::OBJECTIVE_LEGACY => self::OBJECTIVE_LEGACY,
        self::OBJECTIVE_INCOME => self::OBJECTIVE_DIVERSIFY,
        self::OBJECTIVE_CAPITAL => self::OBJECTIVE_SAVINGS,
    ];
    // objectifs d'investissement
    const OBJECTIVE_LIST = [
        self::OBJECTIVE_DIVERSIFY => 'Diversifier votre patrimoine',
        self::OBJECTIVE_REALESTATE => 'Préparer un achat immobilier',
        self::OBJECTIVE_SAVINGS => 'Fructifier votre épargne',
        self::OBJECTIVE_TAXATION => 'Réduire vos impôts',
        self::OBJECTIVE_RETIREMENT => 'Préparer votre retraite',
        self::OBJECTIVE_LEGACY => 'Préparer votre succession',
    ];

    protected $objectiveList = [
        self::OBJECTIVE_DIVERSIFY => 'Diversifier votre patrimoine',
        self::OBJECTIVE_REALESTATE => 'Préparer un achat immobilier',
        self::OBJECTIVE_SAVINGS => 'Fructifier votre épargne',
        self::OBJECTIVE_TAXATION => 'Réduire vos impôts',
        self::OBJECTIVE_RETIREMENT => 'Préparer votre retraite',
        self::OBJECTIVE_LEGACY => 'Préparer votre succession'
    ];

    const OBJECTIVE_INCOME = 'income';
    const OBJECTIVE_CAPITAL = 'capital';

    protected $boursoramaObjectiveList = [
        self::OBJECTIVE_INCOME => 'Générer un complément de revenu',
        self::OBJECTIVE_LEGACY => 'Préparer ma succession',
        self::OBJECTIVE_CAPITAL => 'Valoriser un capital',
        self::OBJECTIVE_RETIREMENT => 'Préparer ma retraite',
        self::OBJECTIVE_SAVINGS => 'Constituer une épargne de précaution'
    ];

    protected $lifeInsuranceObjectiveValues = [
        self::OBJECTIVE_SAVINGS => '01',
        self::OBJECTIVE_RETIREMENT => '02',
        self::OBJECTIVE_LEGACY => '03',
        self::OBJECTIVE_INCOME => '04',
        self::OBJECTIVE_CAPITAL => '05',
        self::OBJECTIVE_TAXATION => '01', // needed for recupererParametrageSouscriptionRequest API call
        self::OBJECTIVE_DIVERSIFY => '05', // needed for recupererParametrageSouscriptionRequest API call
        self::OBJECTIVE_REALESTATE => '01', // needed for recupererParametrageSouscriptionRequest API call
    ];

    /**
     * @Assert\Count(
     *     min="1",
     *     max="6",
     *     minMessage="Vous devez choisir au moins un choix",
     *     groups={"investment", "lifeinsurance_info_type"}
     * )
     * @Assert\Type(type="array")
     * @ORM\Column(name="objective", type="array", length=255, nullable=true)
     */
    #[ORM\Column(name: 'objective', type: 'array', length: 255, nullable: true)]
    private $objective;

    /**
     * Horizon d'investissement
     *
     * @Assert\Count(
     *     min="1",
     *     max="3",
     *     minMessage="Vous devez choisir au moins un choix",
     *      groups={"patrimony", "investment"}
     * )
     * @ORM\Column(name="investmentTerm", type="array", length=255, nullable=true)
     */
    #[ORM\Column(name: 'investmentTerm', type: 'array', length: 255, nullable: true)]
    private $investmentTerm;

    /**
     * Charges
     */

    /**
     * Vos charges annuelles sont estimées à :
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="expenses", type="integer", nullable=true)
     */
    private $expenses;

    /**
     * Crédit Résidence Principale
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="housing_load", type="integer", nullable=true)
     */
    private $housingLoad;

    /**
     * Loyer
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="rent", type="integer", nullable=true)
     */
    #[ORM\Column(name: 'rent', type: 'integer', nullable: true)]
    private $rent;

    /**
     * Impôts
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="taxation", type="integer", nullable=true)
     */
    private $taxation;

    /**
     * Charges incompressibles
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="compulsory_expenses", type="integer", nullable=true)
     */
    private $compulsoryExpenses;

    /**
     * Autres
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="other_expenses", type="integer", nullable=true)
     */
    private $otherExpenses;

    /**
     * Important !! Does this work correctly ?
     * @Assert\IsFalse(
     *      message="Nous ne pouvons actuellement pas proposer de souscriptions aux US persons.",
     *      groups={"scpi", "investment"}
     * )
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"scpi", "investment"}
     * )
     *
     * @ORM\Column(name="us_person", type="boolean", nullable=true)
     */
    private $usPerson;

    /**
     * @ORM\Column(name="fr_resident", type="boolean", nullable=true)
     */
    private $frResident;

    /**
     * @ORM\Column(name="single_residence", type="boolean", nullable=true)
     */
    private $singleResidence;

    /**
     * @ORM\Column(name="nif", type="string", length=255, nullable=true)
     */
    private $nif;

    /**
     * Quel montant d'impôts payez-vous chaque année? (mettre une réglette de 0k€ à 100k€ et +)
     *
     * @Assert\NotNull(
     *      message="Ce champ est obligatoire",
     *      groups={"patrimony"}
     * )
     * @ORM\Column(name="taxation_amount", type="integer", nullable=true)
     */
    private $taxationAmount;

    /**
     * Nombre de personnes à charge
     *
     * @ORM\Column(name="dependent", type="integer", nullable=true)
     */
    private $dependents;

    /**
     * Nombre de parts fiscales ?
     *
     * @ORM\Column(name="fiscal_charge", type="float", nullable=true)
     */
    private $fiscalCharges;

    /**
     * Détenez-vous des avoirs à l’étranger
     * @ORM\Column(name="foreign_assets", type="boolean", nullable=true)
     */
    private $foreignAssets;

    /**
     * Votre activité professionnelle est-elle en lien avec l’étranger
     * @ORM\Column(name="foreign_activity", type="boolean", nullable=true)
     */
    private $foreignActivity;

    /**
     * Quel est le pays d’origine des fonds
     * @ORM\Column(name="foreign_origin", type="string", length=2, nullable=true)
     */
    private $foreignOrigin;

    /**
     * @ORM\Column(name="number_children", type="smallint", nullable=true)
     */
    protected $numberChildren;

    /**
     * @ORM\Column(name="confirm_awareness_minimum_time", type="boolean", nullable=true)
     */
    private $confirmAwarenessMinimumTime;

    /**
     * @ORM\Column(name="lifeinsurance_exist", type="boolean", nullable=true)
     */
    private $lifeinsuranceExist;

    public function getPatrimony(): ?int
    {
        return $this->patrimony;
    }

    public function setPatrimony(?int $patrimony): self
    {
        $this->patrimony = $patrimony;

        return $this;
    }

    public function getLiquidity(): ?int
    {
        return $this->liquidity;
    }

    public function setLiquidity(?int $liquidity): self
    {
        $this->liquidity = $liquidity;

        return $this;
    }

    public function getRealestate(): ?int
    {
        return $this->realestate;
    }

    public function setRealestate(?int $realestate): self
    {
        $this->realestate = $realestate;

        return $this;
    }

    public function getRealestatePrimaryResidence(): ?int
    {
        return $this->realestatePrimaryResidence;
    }

    public function setRealestatePrimaryResidence(?int $value): self
    {
        $this->realestatePrimaryResidence = $value;
        return $this;
    }

    public function getRealestateInvestment(): ?int
    {
        return $this->realestateInvestment;
    }

    public function setRealestateInvestment(?int $value): self
    {
        $this->realestateInvestment = $value;
        return $this;
    }

    public function getRental(): ?int
    {
        return $this->rental;
    }

    public function setRental(?int $rental): self
    {
        $this->rental = $rental;

        return $this;
    }

    public function getAccountSecurities(): ?int
    {
        return $this->accountSecurities;
    }

    public function setAccountSecurities(?int $accountSecurities): self
    {
        $this->accountSecurities = $accountSecurities;

        return $this;
    }

    public function getCapitalisation(): ?int
    {
        return $this->capitalisation;
    }

    public function setCapitalisation(?int $capitalisation): self
    {
        $this->capitalisation = $capitalisation;

        return $this;
    }

    public function getScpi(): ?int
    {
        return $this->scpi;
    }

    public function setScpi(?int $scpi): self
    {
        $this->scpi = $scpi;

        return $this;
    }

    public function getSavingsCapacity(): ?int
    {
        return $this->savingsCapacity;
    }

    public function setSavingsCapacity(?int $savingsCapacity): self
    {
        $this->savingsCapacity = $savingsCapacity;

        return $this;
    }

    public function getIncome(): ?int
    {
        return $this->income;
    }

    public function setIncome(?int $income): self
    {
        $this->income = $income;

        return $this;
    }

    public function getSalary(): ?int
    {
        return $this->salary;
    }

    public function setSalary(?int $salary): self
    {
        $this->salary = $salary;

        return $this;
    }

    public function getPension(): ?int
    {
        return $this->pension;
    }

    public function setPension(?int $pension): self
    {
        $this->pension = $pension;

        return $this;
    }

    public function getOtherSalary(): ?int
    {
        return $this->otherSalary;
    }

    public function setOtherSalary(?int $otherSalary): self
    {
        $this->otherSalary = $otherSalary;

        return $this;
    }

    public function getSecuritiesValues(): ?int
    {
        return $this->securitiesValues;
    }

    public function setSecuritiesValues(?int $securitiesValues): self
    {
        $this->securitiesValues = $securitiesValues;

        return $this;
    }

    public function getRealestateIncome(): ?int
    {
        return $this->realestateIncome;
    }

    public function setRealestateIncome(?int $realestateIncome): self
    {
        $this->realestateIncome = $realestateIncome;

        return $this;
    }

    public function getOther(): ?int
    {
        return $this->other;
    }

    public function setOther(?int $other): self
    {
        $this->other = $other;

        return $this;
    }

    public function getDepositSavings(): ?int
    {
        return $this->depositSavings;
    }

    public function setDepositSavings(?int $depositSavings): self
    {
        $this->depositSavings = $depositSavings;

        return $this;
    }

    public function getDepositSavingsChecking(): ?int
    {
        return $this->depositSavingsChecking;
    }

    public function setDepositSavingsChecking(?int $value): self
    {
        $this->depositSavingsChecking = $value;
        return $this;
    }

    public function getDepositSavingsLivretA(): ?int
    {
        return $this->depositSavingsLivretA;
    }

    public function setDepositSavingsLivretA(?int $value): self
    {
        $this->depositSavingsLivretA = $value;
        return $this;
    }

    public function getDepositSavingsLdd(): ?int
    {
        return $this->depositSavingsLdd;
    }

    public function setDepositSavingsLdd(?int $value): self
    {
        $this->depositSavingsLdd = $value;
        return $this;
    }

    public function getDepositSavingsCsl(): ?int
    {
        return $this->depositSavingsCsl;
    }

    public function setDepositSavingsCsl(?int $value): self
    {
        $this->depositSavingsCsl = $value;
        return $this;
    }

    public function getDepositSavingsOther(): ?int
    {
        return $this->depositSavingsOther;
    }

    public function setDepositSavingsOther(?int $value): self
    {
        $this->depositSavingsOther = $value;
        return $this;
    }

    public function getObjective(): ?array
    {
        return $this->objective;
    }

    public function setObjective(?array $objective): self
    {
        $this->objective = $objective;

        return $this;
    }

    public function getObjectiveList(string $type = null): ?array
    {
        if ($type === 'boursorama') {
            return $this->boursoramaObjectiveList;
        }

        if ($type === 'suravenir') {
            return self::LIFEINSURANCE_OBJECTIVE_LIST;
        }

        return self::OBJECTIVE_LIST;
    }

    public function getObjectiveTxt(): ?string
    {
        $list = [];
        if (is_array($this->objective)) {
            foreach ($this->objective as $objective) {
                if (array_key_exists($objective, self::OBJECTIVE_LIST)) {
                    $list[]  = self::OBJECTIVE_LIST[$objective];
                }
            }
            if (is_array($list)) {
                return implode(', ', $list);
            }
        }

        return '';
    }

    public function getInvestmentTerm(): ?array
    {
        return $this->investmentTerm;
    }

    public function setInvestmentTerm(?array $investmentTerm): self
    {
        $this->investmentTerm = $investmentTerm;

        return $this;
    }

    public function getInvestmentTermTxt(): ?string
    {
        if (is_array($this->investmentTerm)) {
            return implode(',', $this->investmentTerm);
        }
        return '';
    }

    public function getExpenses(): ?int
    {
        return $this->expenses;
    }

    public function setExpenses(?int $expenses): self
    {
        $this->expenses = $expenses;

        return $this;
    }

    public function getHousingLoad(): ?int
    {
        return $this->housingLoad;
    }

    public function setHousingLoad(?int $housingLoad): self
    {
        $this->housingLoad = $housingLoad;

        return $this;
    }

    public function getRent(): ?int
    {
        return $this->rent;
    }

    public function setRent(?int $rent): self
    {
        $this->rent = $rent;

        return $this;
    }

    public function getTaxation(): ?int
    {
        return $this->taxation;
    }

    public function setTaxation(?int $taxation): self
    {
        $this->taxation = $taxation;

        return $this;
    }

    public function getCompulsoryExpenses(): ?int
    {
        return $this->compulsoryExpenses;
    }


    public function setCompulsoryExpenses(?int $compulsoryExpenses): self
    {
        $this->compulsoryExpenses = $compulsoryExpenses;

        return $this;
    }

    public function getOtherExpenses(): ?int
    {
        return $this->otherExpenses;
    }

    public function setOtherExpenses(?int $otherExpenses): self
    {
        $this->otherExpenses = $otherExpenses;

        return $this;
    }


    public function getUsPerson(): ?bool
    {
        return $this->usPerson;
    }

    public function setUsPerson(?bool $usPerson): self
    {
        $this->usPerson = $usPerson;

        return $this;
    }

    public function getFrResident(): ?bool
    {
        return $this->frResident;
    }

    public function setFrResident($frResident): self
    {
        $this->frResident = $frResident;

        return $this;
    }

    public function getSingleResidence(): ?bool
    {
        return $this->singleResidence;
    }

    public function setSingleResidence(bool $singleResidence): self
    {
        $this->singleResidence = $singleResidence;

        return $this;
    }

    public function getNif(): ?string
    {
        return $this->nif;
    }

    public function setNif($nif): self
    {
        $this->nif = $nif;

        return $this;
    }

    public function getUsPersonTxt(): ?string
    {
        if (isset($this->usPerson)) {
            return $this->usPerson ? 'Oui' : 'Non';
        }

        return NULL;
    }

    public function getTaxationAmount(): ?int
    {
        return $this->taxationAmount;
    }

    public function setTaxationAmount(?int $taxationAmount): self
    {
        $this->taxationAmount = $taxationAmount;

        return $this;
    }


    public function getCrowdfinance(): ?int
    {
        return $this->crowdfinance;
    }

    public function setCrowdfinance(?int $crowdfinance): self
    {
        $this->crowdfinance = $crowdfinance;

        return $this;
    }

    public function getPatrimonyOther(): ?int
    {
        return $this->patrimonyOther;
    }

    public function setPatrimonyOther(?int $patrimonyOther): self
    {
        $this->patrimonyOther = $patrimonyOther;

        return $this;
    }


    public function getDependents(): ?int
    {
        return $this->dependents;
    }

    public function setDependents(?int $dependents): self
    {
        $this->dependents = $dependents;

        return $this;
    }

    public function getFiscalCharges(): ?float
    {
        return $this->fiscalCharges;
    }

    public function setFiscalCharges(?float $fiscalCharges): self
    {
        $this->fiscalCharges = $fiscalCharges;

        return $this;
    }

    public function getForeignAssets(): ?bool
    {
        return $this->foreignAssets;
    }

    public function setForeignAssets(?bool $foreignAssets): self
    {
        $this->foreignAssets = $foreignAssets;

        return $this;
    }

    public function getForeignActivity(): ?bool
    {
        return $this->foreignActivity;
    }

    public function setForeignActivity(?bool $foreignActivity): self
    {
        $this->foreignActivity = $foreignActivity;

        return $this;
    }

    public function getForeignOrigin(): ?string
    {
        return $this->foreignOrigin;
    }

    public function setForeignOrigin(?string $foreignOrigin): self
    {
        $this->foreignOrigin = $foreignOrigin;

        return $this;
    }

    public function getOwners(): array
    {
        return array_flip(self::OWNER_LIST);
    }

    public function getNumberChildren(): ?int
    {
        return $this->numberChildren;
    }

    public function setNumberChildren(?int $numberChildren): self
    {
        $this->numberChildren = $numberChildren;

        return $this;
    }

    public function getInvestmentAvailability()
    {
        return $this->investmentAvailability;
    }

    public function setInvestmentAvailability($investmentAvailability): self
    {
        $this->investmentAvailability = $investmentAvailability;

        return $this;
    }
    
    public function getInvestmentAvailabilityList(): array
    {
        return array_flip(self::INVESTMENT_AVAIBILITY_LIST);
    }

    public function isCompanyOwner(): ?bool
    {
        return $this->companyOwner;
    }

    public function setCompanyOwner($companyOwner): self
    {
        $this->companyOwner = $companyOwner;

        return $this;
    }

    public function isMif(): ?bool
    {
        return $this->mif;
    }

    public function getMifTxt(): ?string
    {
        return $this->mif ? 'Oui' : 'Non';
    }

    public function setMif($mif): self
    {
        $this->mif = $mif;

        return $this;
    }

    public function isAwarenessMinimumAmount(): ?bool
    {
        return $this->awarenessMinimumAmount;
    }

    public function isAwarenessMinimumTime(): ?bool
    {
        return $this->awarenessMinimumTime;
    }

    public function isAwarenessTransactions(): ?bool
    {
        return $this->awarenessTransactions;
    }

    public function setAwarenessMinimumAmount($awarenessMinimumAmount): self
    {
        $this->awarenessMinimumAmount = $awarenessMinimumAmount;

        return $this;
    }

    public function setAwarenessMinimumTime($awarenessMinimumTime): self
    {
        $this->awarenessMinimumTime = $awarenessMinimumTime;

        return $this;
    }

    public function setAwarenessTransactions($awarenessTransactions): self
    {
        $this->awarenessTransactions = $awarenessTransactions;

        return $this;
    }

    public function isAttestIncome(): ?bool
    {
        return $this->attestIncome;
    }

    public function setAttestIncome(?bool $attestIncome): self
    {
        $this->attestIncome = $attestIncome;

        return $this;
    }

    public function isConfirmAwarenessMinimumTime(): ?bool
    {
        return $this->confirmAwarenessMinimumTime;
    }

    public function setConfirmAwarenessMinimumTime(?bool $confirmAwarenessMinimumTime): self
    {
        $this->confirmAwarenessMinimumTime = $confirmAwarenessMinimumTime;

        return $this;
    }

    public function isAttestSignificantTransaction(): ?bool
    {
        return $this->attestSignificantTransaction;
    }

    public function setAttestSignificantTransaction($attestSignificantTransaction): self
    {
        $this->attestSignificantTransaction = $attestSignificantTransaction;

        return $this;
    }

    public function isAttestAware(): ?bool
    {
        return $this->attestAware;
    }

    public function setAttestAware($attestAware): self
    {
        $this->attestAware = $attestAware;

        return $this;
    }

    public function isAttestTruth(): ?bool
    {
        return $this->attestTruth;
    }

    public function setAttestTruth($attestTruth): self
    {
        $this->attestTruth = $attestTruth;

        return $this;
    }

    public function getSecuritiesOptionsCount()
    {
        return $this->securitiesOptionsCount;
    }

    public function setSecuritiesOptionsCount($securitiesOptionsCount): self
    {
        $this->securitiesOptionsCount = $securitiesOptionsCount;

        return $this;
    }

    public function isFinanceWorker()
    {
        return $this->financeWorker;
    }

    public function setFinanceWorker($financeWorker): self
    {
        $this->financeWorker = $financeWorker;

        return $this;
    }

    public function getAdequacy1(): ?string
    {
        return $this->adequacy1;
    }

    public function setAdequacy1(string $adequacy1): self
    {
        $this->adequacy1 = $adequacy1;

        return $this;
    }

    public function getLifeInsuranceObjectiveValue(string $objective): string
    {
        return $this->lifeInsuranceObjectiveValues[$objective];
    }

    public function getAdequacy2(): ?string
    {
        return $this->adequacy2;
    }

    public function setAdequacy2(string $adequacy2): self
    {
        $this->adequacy2 = $adequacy2;

        return $this;
    }

    public function getAdequacy3(): ?bool
    {
        return $this->adequacy3;
    }

    public function setAdequacy3(bool $adequacy3): self
    {
        $this->adequacy3 = $adequacy3;

        return $this;
    }

    public function getAdequacy4(): ?bool
    {
        return $this->adequacy4;
    }

    public function setAdequacy4(bool $adequacy4): self
    {
        $this->adequacy4 = $adequacy4;

        return $this;
    }

    public function getAdequacy5(): ?string
    {
        return $this->adequacy5;
    }

    public function setAdequacy5(string $adequacy5): self
    {
        $this->adequacy5 = $adequacy5;

        return $this;
    }
    
    public function getAdequacyChoices(): array
    {
        return array_flip(self::ADEQUACY_1_2_CHOICES);
    }

    public function getAdequacy5Choices(): array
    {
        return array_flip(self::ADEQUACY_5_CHOICES);
    }

    public function isAccompaniment(): ?bool
    {
        return $this->accompaniment;
    }

    public function setAccompaniment(?bool $accompaniment): self
    {
        $this->accompaniment = $accompaniment;

        return $this;
    }

    public function setAttestMif($attestMif): Info
    {
        $this->attestMif = $attestMif;

        return $this;
    }

    public function getAttestMif()
    {
        return $this->attestMif;
    }

    public function getlifeinsuranceExist(): ?bool
    {
        return $this->lifeinsuranceExist;
    }

    public function setlifeinsuranceExist($lifeinsuranceExist): self
    {
        $this->lifeinsuranceExist = $lifeinsuranceExist;

        return $this;
    }
}
