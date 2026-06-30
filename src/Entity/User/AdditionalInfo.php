<?php

namespace App\Entity\User;

use Doctrine\ORM\Mapping as ORM;


#[ORM\Table(name: 'user_additional_fields')]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]

class AdditionalInfo
{
    /**
     * Field : codeCapaciteJuridique
     */
    const LEGAL_CAPACITY = [
        '01' => 'Mineur/adm légale des parents',
        '02' => 'Mineur/adm légale/contr.jud',
        '03' => 'Mineur/tutelle P. Physique',
        '04' => 'Mineur/tutelle P. Morale',
        '05' => 'Mineur emancipe',
        '10' => 'Majeur capable',
        '11' => 'Majeur/tutelle P. Physique',
        '12' => 'Majeur/tutelle P. Morale',
        '13' => 'Majeur/curatelle P. Physique',
        '14' => 'Majeur/curatelle P. Morale',
        '15' => 'Majeur/sauvegarde de justice',
        '16' => 'Majeur tut. prest. sociales P.Mora',
        '17' => 'Majeur/curat. renforcee P. Physiq',
        '18' => 'Majeur/curat. renforcee P. Morale',
    ];

    /**
     * Field : Categorie Socio Professionnelle
     */
    const PROFESSIONAL_SOCIAL_CATEGORY = [
        '1000' => 'Agriculteurs exploitants',
        '2100' => 'Artisans',
        '2200' => 'Commerçants et assimilés',
        '2300' => "Chefs d'entreprise de 10 salariés ou plus",
        '3100' => "Professions libérales et assimilés",
        '3200' => "Cadres de la fonction publique, professions, intellectuelles et artistiques",
        '3600' => "Cadres d'entreprise",
        '4100' => "Professions intermédiaires de l'enseignement, de la santé, de la fonction publique et assimilés",
        '4600' => "Professions intermédiaires administratives et commerciales des entreprises",
        '4700' => "Techniciens",
        '4800' => "Contremaîtres, agents de maîtrise",
        '5100' => "Employés de la fonction publique",
        '5400' => "Employés administratifs d'entreprise",
        '5500' => "Employés de commerce",
        '5600' => "Personnels des services directs aux particuliers",
        '6100' => "Ouvriers qualifiés",
        '6600' => "Ouvriers non qualifiés",
        '6900' => "Ouvriers agricoles",
        '7100' => "Anciens agriculteurs exploitants",
        '7200' => "Anciens artisans, commerçants, chefs d'entreprise",
        '7300' => "Anciens cadres et professions intermédiaires",
        '7600' => "Anciens employés et ouvriers",
        '7900' => "Retraités ancienne activité inconnue",
        '8100' => "Demandeurs d'emploi",
        '8200' => "Inactifs divers (autres que retraités)",
        '8400' => "Élèves, Étudiants, Apprentis",
    ];

    /**
     * Field : Régumé Matrimonial
     */
    const MATRIMONIAL_REGIME = [
        '1' => 'Communauté légal. réduit aux acquets',
        '2' => 'Communauté meubles et acquets',
        '3' => 'Séparation des biens',
        '4' => 'Participation aux acquets',
        '5' => 'Communauté universelle',
        '6' => 'Autres',
        '7' => 'Communauté univ. attrib. intégrale',
    ];

    /**
     * Field : Tranches Revenus Annuels
     */
    const ANNUAL_INCOME = [
        '11' => 'Moins de 15 000€',
        '12' => '15 000€ à 30 000€',
        '13' => '30 000€ à 45 000€',
        '14' => '45 000€ à 60 000€',
        '15' => '60 000€ à 100 000€',
        '16' => '100 000€ à 150 000€',
        '17' => 'Supérieur à 150 000€',
    ];

    /**
     * Field : Tranches Revenus Annuels
     */
    const PATRIMONY_AMOUNT = [
        '11' => 'Moins de 25 000€',
        '12' => '25 000€ à 50 000€',
        '13' => '50 000€ à 100 000€',
        '14' => '100 000€ à 150 000€',
        '15' => '150 000€ à 300 000€',
        '16' => '300 000€ à 450 000€',
        '17' => '450 000€ à 600 000€',
        '18' => '600 000€ à 750 000€',
        '19' => '750 000€ à 1 500 000€',
        '20' => 'Supérieur à 1 500 000€',
    ];

    /**
     * Field : Statut professionnel
     */
    const PROFESSIONAL_STATUS = [
        '01' => 'Actif',
        '02' => "Demandeur d'emploi",
        '03' => 'Etudiant / Eleve / Apprenti',
        '04' => 'Retraite',
        '05' => 'Autres inactifs',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\OneToOne(targetEntity: 'User', inversedBy: 'additionalInfo')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]

    private $user = null;


    #[ORM\Column(name: 'legal_capacity', type: 'string', nullable: true)]

    private $legalCapacity;

    #[ORM\Column(name: 'professional_social_category', type: 'string', length: 32, nullable: true)]

    private $professionalSocialCategory;

    #[ORM\Column(name: 'matrimonial_regime', type: 'string', length: 2, nullable: true)]

    private $matrimonialRegime;

    #[ORM\Column(name: 'annual_income', type: 'string', length: 2, nullable: true)]

    private $annualIncome;

    #[ORM\Column(name: 'patrimony_amount', type: 'string', length: 2, nullable: true)]

    private $patrimonyAmount;

    #[ORM\Column(name: 'professional_status', type: 'string', length: 2, nullable: true)]

    private $professionalStatus;


    public function getId()
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getLegalCapacity(): ?string
    {
        return $this->legalCapacity;
    }

    public function setLegalCapacity(string $legalCapacity): self
    {
        $this->legalCapacity = $legalCapacity;

        return $this;
    }

    public function getLegalCapacityList(): array
    {
        return self::LEGAL_CAPACITY;
    }

    public function getProfessionalSocialCategory(): ?string
    {
        return $this->professionalSocialCategory;
    }

    public function setProfessionalSocialCategory(string $professionalSocialCategory): self
    {
        $this->professionalSocialCategory = $professionalSocialCategory;

        return $this;
    }

    public function getProfessionalSocialCategoryList(): array
    {
        return self::PROFESSIONAL_SOCIAL_CATEGORY;
    }

    public function getMatrimonialRegime(): ?string
    {
        return $this->matrimonialRegime;
    }

    public function setMatrimonialRegime(?string $matrimonialRegime): self
    {
        $this->matrimonialRegime = $matrimonialRegime;

        return $this;
    }

    public function getMatrimonialRegimeList(): array
    {
        return self::MATRIMONIAL_REGIME;
    }

    public function getMatrimonialRegimeListByMaritalStatus(?string $maritalStatusKey): array
    {
        switch ($maritalStatusKey) {
            case "0": // Célibataire
            case "1": // Divorcé(e)
            case "4": // Pacsé(e) avec contrat
            case "5": // Pacsé(e) sans contrat
            case "6": // Veuf(ve)
            case "10": // Séparé(e)
            case "11": // Union autre
                return [
                    '6' => 'Autres',
                ];
                break;
            case "2": // Marié(e) avec contrat de mariage
            case "3": // Marié(e) sans contrat de mariage
            case "7": // Marié(e) avec contrat de mariage (communauté universelle)
            case "8": // Marié(e) avec contrat de mariage (communauté réduite aux acquêts)
            case "9":  // Marié(e) avec contrat de mariage (régime séparatiste)
                return [
                    '1' => 'Communauté légal. réduit aux acquets',
                    '2' => 'Communauté meubles et acquets',
                    '3' => 'Séparation des biens',
                    '4' => 'Participation aux acquets',
                    '5' => 'Communauté universelle',
                    '7' => 'Communauté univ. attrib. intégrale',
                ];
                break;
            default:
                return [
                    '1' => 'Communauté légal. réduit aux acquets',
                    '2' => 'Communauté meubles et acquets',
                    '3' => 'Séparation des biens',
                    '4' => 'Participation aux acquets',
                    '5' => 'Communauté universelle',
                    '6' => 'Autres',
                    '7' => 'Communauté univ. attrib. intégrale',
                ];
        }
    }

    public function getAnnualIncome(): ?string
    {
        return $this->annualIncome;
    }

    public function setAnnualIncome(string $annualIncome): self
    {
        $this->annualIncome = $annualIncome;

        return $this;
    }

    public function getAnnualIncomeList(): array
    {
        return self::ANNUAL_INCOME;
    }

    public function getPatrimonyAmount(): ?string
    {
        return $this->patrimonyAmount;
    }

    public function setPatrimonyAmount(string $patrimonyAmount): self
    {
        $this->patrimonyAmount = $patrimonyAmount;

        return $this;
    }

    public function getPatrimonyAmountList(): array
    {
        return self::PATRIMONY_AMOUNT;
    }

    public function getProfessionalStatus(): ?string
    {
        return $this->professionalStatus;
    }

    public function setProfessionalStatus(string $professionalStatus): self
    {
        $this->professionalStatus = $professionalStatus;

        return $this;
    }

    public function getProfessionalStatusList(): array
    {
        return self::PROFESSIONAL_STATUS;
    }
}
