<?php

namespace App\Entity\User\Info;

use App\Services\User\Info as UserInfo;


/**
 * set fields and their getters
 */
trait Fields
{
    /**
     * List of fields that are allowed to be set data from the API
     */
    public function getApiExternalFieldSetter(): array
    {
        return [
            'owner' => [
                'required' => false,
                'type' => 'array',
                'title' => 'Êtes-vous propriétaire de',
                'values' => $this->getOwners(),
            ],
            'sourceOfFunds' => [
                'required' => false,
                'type' => 'array',
                'title' => "D'où provient le montant que vous souhaitez investir ?",
                'values' => UserInfo::getSourceOfFundsChoices(),
            ],

            /**
             * Patrimony
             */
            'patrimony' => [
                'required' => false,
                'type' => 'integer',
                'title' => "Quel est le montant estimé de votre patrimoine ?",
                'min' => 0,
                'max' => 1000000000,
            ],
            'liquidity' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'patrimony',
                'info' => "Patrimony breakdown in percentage. Total should equal 100%",
                'title' => "Liquidités",
                'min' => 0,
                'max' => 100,
            ],
            'realestate' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'patrimony',
                'info' => "Patrimony breakdown in percentage. Total should equal 100%",
                'title' => "Immobilier de jouissance",
                'min' => 0,
                'max' => 100,
            ],
            'accountSecurities' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'patrimony',
                'info' => "Patrimony breakdown in percentage. Total should equal 100%",
                'title' => "Compte titre",
                'min' => 0,
                'max' => 100,
            ],
            'rental' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'patrimony',
                'info' => "Patrimony breakdown in percentage. Total should equal 100%",
                'title' => "Immobilier locatif (Hors SCPI)",
                'min' => 0,
                'max' => 100,
            ],
            'capitalisation' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'patrimony',
                'info' => "Patrimony breakdown in percentage. Total should equal 100%",
                'title' => "Assurance-vie et capitalisation",
                'min' => 0,
                'max' => 100,
            ],
            'scpi' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'patrimony',
                'info' => "Patrimony breakdown in percentage. Total should equal 100%",
                'title' => "SCPI",
                'min' => 0,
                'max' => 100,
            ],
            'crowdfinance' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'patrimony',
                'info' => "Patrimony breakdown in percentage. Total should equal 100%",
                'title' => "Crowdfunding et crowdlending",
                'min' => 0,
                'max' => 100,
            ],
            'patrimonyOther' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'patrimony',
                'info' => "Patrimony breakdown in percentage. Total should equal 100%",
                'title' => "Autres",
                'min' => 0,
                'max' => 100,
            ],

            /**
             * Income
             */
            'income' => [
                'required' => false,
                'type' => 'integer',
                'title' => 'Vos revenus annuels sont estimés à',
                'min' => 0,
                'max' => 2000000
            ],
            'salary' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'income',
                'info' => "Salary breakdown in percentage. Total should equal 100%",
                'title' => "Salaires",
                'min' => 0,
                'max' => 100,
            ],
            'pension' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'income',
                'info' => "Salary breakdown in percentage. Total should equal 100%",
                'title' => "Pensions de retraite",
                'min' => 0,
                'max' => 100,
            ],
            'otherSalary' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'income',
                'info' => "Salary breakdown in percentage. Total should equal 100%",
                'title' => "Autres revenus professionnels",
                'min' => 0,
                'max' => 100,
            ],
            'securitiesValues' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'income',
                'info' => "Salary breakdown in percentage. Total should equal 100%",
                'title' => "Revenus de valeurs mobilières",
                'min' => 0,
                'max' => 100,
            ],
            'realestateIncome' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'income',
                'info' => "Salary breakdown in percentage. Total should equal 100%",
                'title' => "Revenus fonciers",
                'min' => 0,
                'max' => 100,
            ],
            'other' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'income',
                'info' => "Salary breakdown in percentage. Total should equal 100%",
                'title' => "Autres",
                'min' => 0,
                'max' => 100,
            ],

            /**
             * Savings
             */
            'savingsCapacity' => [
                'required' => false,
                'type' => 'integer',
                'title' => "Quelle est votre capacité d'épargne annuelle ?",
                'min' => 0,
                'max' => 2000000
            ],
            'objective' => [
                'required' => true,
                'type' => 'array',
                'title' => "Vos objectifs principaux d'investissement sont",
                'values' => array_flip($this->getObjectiveList('boursorama')),
            ],
            'investmentTerm' => [
                'required' => true,
                'type' => 'array',
                'title' => "Votre objectif d'investissement",
                'values' => [
                    '0 - 3 ans' => 0,
                    '3 - 8 ans' => 1,
                    '> 8 ans' => 2,
                ],
            ],

            /**
             * Expenses
             */
            'expenses' => [
                'required' => false,
                'type' => 'integer',
                'title' => "Vos charges annuelles sont estimées à",
                'min' => 0,
                'max' => 200000
            ],
            'housingLoad' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'expenses',
                'info' => "Expenses breakdown in percentage. Total should equal 100%",
                'title' => "Crédit Résidence Principale",
                'min' => 0,
                'max' => 100,
            ],
            'rent' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'expenses',
                'info' => "Expenses breakdown in percentage. Total should equal 100%",
                'title' => "Loyer",
                'min' => 0,
                'max' => 100,
            ],
            'taxation' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'expenses',
                'info' => "Expenses breakdown in percentage. Total should equal 100%",
                'title' => "Impôts",
                'min' => 0,
                'max' => 100,
            ],
            'compulsoryExpenses' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'expenses',
                'info' => "Expenses breakdown in percentage. Total should equal 100%",
                'title' => "Charges incompressibles",
                'min' => 0,
                'max' => 100,
            ],
            'otherExpenses' => [
                'required' => false,
                'type' => 'integer',
                'elementKey' => 'expenses',
                'info' => "Expenses breakdown in percentage. Total should equal 100%",
                'title' => "Autres",
                'min' => 0,
                'max' => 100,
            ],

            /**
             * Taxation
             */
            'taxationAmount' => [
                'required' => false,
                'type' => 'integer',
                'title' => "Quel montant d'impôts payez-vous chaque année ?",
                'min' => 0,
                'max' => 100000
            ],


            /**
             * Investments page
             */
            'alreadyInvest' => [
                'required' => false,
                'type' => 'boolean',
                'title' => "Avez-vous déjà investi dans l'immobilier ?",
                'values' => UserInfo::getBooleanChoices(),
            ],
            'investType' => [
                'required' => false,
                'type' => 'array',
                'title' => "Si oui, quel type d'investissement ?",
                'values' => UserInfo::getInvestTypeChoices(),
            ],
            'investorQualified' => [
                'required' => true,
                'type' => 'boolean',
                'title' => "Êtes-vous un investisseur qualifié ?",
                'values' => UserInfo::getBooleanChoices(),
            ],
            'patrimonyPercent' => [
                'required' => true,
                'type' => 'boolean',
                'title' => "Le montant de l'investissement que vous projetez de réaliser représente-t-il moins de 10 % de votre patrimoine total ?",
                'values' => UserInfo::getBooleanChoices(),
            ],
            'securities' => [
                'required' => false,
                'type' => 'boolean',
                'title' => "Avez-vous déjà investi dans des titres financiers ?",
                'values' => UserInfo::getBooleanChoices(),
            ],
            'securitiesOptions' => [
                'required' => false,
                'type' => 'array',
                'title' => "Si oui, quels types de titres ?",
                'values' => UserInfo::getSecuritiesTypesChoices(),
            ],

            'futurInvest' => [
                'required' => false,
                'type' => 'boolean',
                'title' => "Dans les 12 prochains mois, vous prévoyez d'investir ?",
                'values' => UserInfo::getBooleanChoices(),
            ],

            /**
             * Taxation/others
             */
            'isf' => [
                'required' => true,
                'type' => 'boolean',
                'title' => "Êtes-vous assujetti à l'IFI ?",
                'values' => UserInfo::getBooleanChoices(),
            ],
            'political' => [
                'required' => true,
                'type' => 'boolean',
                'title' => "Êtes-vous, ou avez-vous été, (ou une personne de votre entourage) une personne politiquement exposée ?",
                'values' => UserInfo::getBooleanChoices(),
            ],
            'usPerson' => [
                'required' => true,
                'type' => 'boolean',
                'title' => "Êtes-vous une US personne ?",
                'values' => UserInfo::getBooleanChoices(),
            ]
        ];
    }
}
