<?php

namespace App\Services\User;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class Info
{
    const BOOLEAN_TYPE = [
        true => 'Oui',
        false => 'Non'
    ];

    const OWNER_CHOICES = [
        'Résidence principale',
        'Résidence secondaire',
        'Bien destiné à de l\'investissement locatif',
        'Aucun',
    ];

    const EARNINGS_CHOICES = [
        'Salaires',
        'Revenus fonciers',
        'Pensions, retraites, rentes...',
        'Autre',
    ];

    const EARNINGS_AMOUNT_CHOICES = [
        '30 000 € <',
        '30 000 – 50 000 €',
        '50 000 – 70 000 €',
        '> 70 000 €',
    ];

    const THRIFT_CHOICES = [
        '5 000 € <',
        '5 000 – 20 000 €',
        '20 000 – 50 000 €',
        '> 50 000 €',
    ];

    const PATRIMONY_CHOICES = [
        '< 100 000 €',
        '100 000 – 300 000 €',
        '300 000 – 600 000 €',
        '> 600 000 €',
    ];

    const INVEST_TYPE_CHOICES = [
        "Le crowdfunding",
        'Les SCPI',
        "L'investissement locatif",
        'Autre',
    ];

    const SOURCE_OF_FUNDS_CHOICES = [
        'Épargne',
        'Donation',
        'Salaire',
        'Gains',
        'Autre',
    ];

    const LIFEINSURANCE_SOURCE_OF_FUNDS_CONVERT_VALUES_TO_HOMUNITY_VALUES = [
        12 => 1, // suravenir : héritage/donation => homunity : donation
        13 => 4, // suravenir : vente d'actifs immobiliers => homunity : autre
        19 => 4, // suravenir : capitaux d'activité pro. => homunity : autre
        21 => 4, // suravenir : cession de bien => homunity : autre
        22 => 0, // suravenir : épargne déjà constituée => homunity : épargne
        23 => 3, // suravenir : gains aux jeux => homunity : gains
        24 => 4, // suravenir : indemnisation/dommages intérêts => homunity : autre
        28 => 4, // suravenir : crédit => homunity : autre
    ];

    const SECURITIES_TYPES_CHOICES = [
        "Obligations",
        "Actions côtées",
        "Actions non côtées hors financement participatif",
        "Actions non côtées dans le cadre d'un financement participatif",
        "Titres non côtées via un fonds d'investissement (FCPR, FCPI ..)",
        "OPCVM",
        "Prêts dans le cadre d’un financement participatif",
    ];

    const SECURITIES_TYPES_COUNT_CHOICES = [
        "Moins de 3",
        "Entre 3 et 6",
        "Plus de 6",
    ];

    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function getOwnerChoices(): array
    {
        return array_flip(self::OWNER_CHOICES);
    }

    public static function getEarningsChoices(): array
    {
        return array_flip(self::EARNINGS_CHOICES);
    }

    public static function getEarningsAmountChoices(): array
    {
        return array_flip(self::EARNINGS_AMOUNT_CHOICES);
    }

    public static function getThriftChoices(): array
    {
        return array_flip(self::THRIFT_CHOICES);
    }

    public static function getPatrimonyChoices(): array
    {
        return array_flip(self::PATRIMONY_CHOICES);
    }

    public static function getInvestTypeChoices(): array
    {
        return array_flip(self::INVEST_TYPE_CHOICES);
    }

    public static function getSourceOfFundsChoices(): array
    {
        return array_flip(self::SOURCE_OF_FUNDS_CHOICES);
    }

    public static function getSecuritiesTypesChoices(): array
    {
        return array_flip(self::SECURITIES_TYPES_CHOICES);
    }

    public static function getSecuritiesTypesCountChoices(): array
    {
        return array_flip(self::SECURITIES_TYPES_COUNT_CHOICES);
    }

    public static function getBooleanChoices(): array
    {
        return array_flip(self::BOOLEAN_TYPE);
    }

    /**
     * @param UserInterface $user
     * @return void
     * Calcul les totaux annuels brut pour le KYC step 3
     */
    public function calculateUserFinancialValues(UserInterface $user): void
    {
        $userInfo = $user->getInfo();
        $income = $userInfo->getSalary() + $userInfo->getRealestateIncome();
        $userInfo->setIncome($income);
        $patrimony = $userInfo->getRealestate() + $userInfo->getRental();
        $userInfo->setPatrimony($patrimony);
        $userInfo->setExpenses($userInfo->getRent());
        $this->em->persist($userInfo);
        $this->em->flush();
    }
}
