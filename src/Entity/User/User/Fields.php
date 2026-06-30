<?php

namespace App\Entity\User\User;

use App\Entity\User\User;
use App\Services\Localization\Department;

/**
 * set fields and their getters
 */
trait Fields
{
    public function getApiFields(User $user): ?array
    {
        $fields = array_keys($this->getApiExternalFieldSetter());

        $data = [];
        foreach ($fields as $field) {
            $method = 'get' . ucfirst($field);
            $data[$field] = $user->$method();
        }
        return
            $data +
            [
                'userId' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
            ];
        /*return [
            'userId' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            //'isInProject' => $user->getPrivateProjectIds(),

        ];*/
    }

    public function getExportTitle()
    {
        return [
            '#',
            'Nom',
            'Prénom',
            'Email',
            'Activé',
            'Identifié',
            'Externe',
            'Créé le',
            'Dernière connexion',
            'Date de naissance',
            'Premier investissement',
            'Nationalité',
            'Téléphone',
            'Adresse',
            'Adresse comp.',
            'Code postal',
            'Pays',
            'Statut marital',
            'Profession',
            'ID Mangopay',
            'ID Wallet',
            'Type',
            'Source',
            'KYC validés',
            'Patrimoine',
            'Patrimoine montant',
            'Revenu',
            'Revenu montant',
            "Capacité d'épargne",
            "Capacité d'épargne montant",
            "Provenance des fonds",
            "Investissement projeté <10%",
            "Déjà investi dans l'immobilier",
            "Investissement déjà opérés",
            "Politiquement exposé",
            "US personne",
            "UTM Source",
            "UTM Medium",
            "UTM Campaign",
            "UTM Content",
        ];
    }

    public function getExportFields(): array
    {
        $user = [
            $this->getId(),
            $this->getLastname(),
            $this->getFirstname(),
            $this->getEmail(),
            $this->isEnabled() ? 'Oui' : 'Non',
            $this->getIdentified() ? 'Oui' : 'Non',
            $this->isExternal() ? 'Oui' : 'Non',
            $this->getFormattedDate($this->getCreatedAt()),
            $this->getFormattedDate($this->getLastLogin()),
            $this->getFormattedDate($this->getBirthday()),
        ];

        $getters = [
            'nationality',
            'phone',
            'address',
            'addressLine1',
            'postalCode',
            'country',
            'maritalStatusName',
            'professionName',
            'currentMangoPayId',
            'mangopayWalletId',
            'typeName',
            'fullSource'
        ];

        foreach ($getters as $getter) {
            $user[] = $this->getValueIfExist($getter);
        }

        $user[] = $this->getUseridentified();

        if (!empty($this->getInfo())) {
            $info = $this->getInfo();

            $getters = [
                'patrimonyAmountTxt',
                'patrimony',
                'earningAmountTxt',
                'income',
                'thriftAmountTxt',
                'savingsCapacity',
                'sourceOfFundsExport',
                'patrimonyPercentTxt',
                'alreadyInvestTxt',
                'investTypeTxt',
                'politicalTxt',
                'usPersonTxt'
            ];

            foreach ($getters as $getter) {
                $getter = 'get' . ucfirst($getter);
                $val = empty($info->$getter()) ? '-' : $info->$getter();
                $user[] = str_replace(';', ',', $val);
            }
        } else {
            for ($i = 0; $i <= 11; $i++) {
                $user[] = '-';
            }
        }

        if (!empty($marketing = $this->getMarketing())) {
            
            $getters = [
                'utmSource',
                'utmMedium',
                'utmCampaign',
                'utmContent',
            ];

            foreach ($getters as $getter) {
                $getter = 'get' . ucfirst($getter);
                $val = empty($marketing->$getter()) ? '-' : $marketing->$getter();
                $user[] = str_replace(';', ',', $val);
            }
            
        } else {
            for ($i = 0; $i <= 11; $i++) {
                $user[] = '-';
            }
        }
        
        return $user;
    }

    private function getValueIfExist($getter, $entity = null)
    {
        $entity = empty($entity) ? $this : $entity;

        if (property_exists($entity, $getter) && !empty($entity->$getter)) {
            return $entity->$getter;
        }

        return '-';
    }

    private function getFormattedDate(?\DateTime $dateTime)
    {
        if ($dateTime instanceof \DateTime) {
            return $dateTime->format('Y-m-d H:i:s');
        }
    }

    public function getStatisticsExportTitle()
    {
        return [
            'userId',
            'Email',
            'Date de naissance',
            'Age',
            'Profession',
            'Pays',
            'Ville',
            'Total Investi',
            "Nombre d'investissements",
            'Investissement moyen'
        ];
    }

    public function getStatisticsExportFields()
    {
        $total = $this->getTotalInvest();

        return [
            $this->getId(),
            $this->getEmail(),
            $this->getBirthday() ? $this->getBirthday()->format('Y-m-d') : '',
            $this->getBirthday() ? floor((time() - strtotime($this->getBirthday()->format('Y-m-d'))) / 31556926): '',
            $this->getProfessionName(),
            $this->getCountry(),
            $this->getCity(),
            $total['sum'],
            $total['number'],
            $total['average'],
        ];
    }


    /**
     * List of fields that are allowed to be set data from the API
     */
    public function getApiExternalFieldSetter(): array
    {
        return [
            'email' => [
                'required' => true,
                'type' => 'string',
                'title' => 'E-mail',
                'maxLength' => 180,
            ],
            'firstname' => [
                'required' => true,
                'type' => 'string',
                'title' => 'Prénom',
                'maxLength' => 45,
            ],
            'lastname' => [
                'required' => true,
                'type' => 'string',
                'title' => 'Nom',
                'maxLength' => 45,
            ],
            'birthFirstname' => [
                'required' => false,
                'type' => 'string',
                'title' => 'Prénom de naissancce',
                'maxLength' => 45,
            ],
            'birthLastname' => [
                'required' => false,
                'type' => 'string',
                'title' => 'Nom de naissance',
                'maxLength' => 45,
            ],
            'gender' => [
                'required' => true,
                'type' => 'string',
                'title' => 'Genre',
                'values' => [
                    'Monsieur' => 'MAN',
                    'Madame' => 'WOMAN',
                ],
                'maxLength' => 5,
            ],
            'birthday' => [
                'required' => true,
                'type' => 'datetime',
                'title' => 'Date de naissance',
                'format' => 'Y-m-d',
            ],
            'birthplace' => [
                'required' => true,
                'type' => 'string',
                'title' => 'Ville de naissance',
                'info' => 'ISO 3166-1 alpha-2',
                'maxLength' => 255,
            ],
            'birthCountry' => [
                'required' => true,
                'type' => 'string',
                'title' => 'Pays de naissance',
                'info' => 'ISO 3166-1 alpha-2',
                'maxLength' => 2,
            ],
            'birthDepartment' => [
                'required' => true,
                'type' => 'string',
                'title' => 'Département de naissance',
                'values' => Department::getFrenchDepartments(),
                'maxLength' => 10,
            ],
            'nationality' => [
                'required' => true,
                'type' => 'string',
                'title' => 'Nationalité',
                'info' => 'ISO 3166-1 alpha-2',
                'maxLength' => 2,
            ],
            'phone' => [
                'required' => true,
                'type' => 'string',
                'title' => 'Téléphone portable',
                'format' => '+xxxxxxxxxxx',
                'info' => 'required for contrats',
                'maxLength' => 15,
            ],
            'addressLine1' => [
                'required' => true,
                'type' => 'string',
                'title' => 'Adresse',
                'maxLength' => 255,
            ],
            'taxAddress' => [
                'required' => false,
                'type' => 'string',
                'title' => 'Adresse fiscale',
                'maxLength' => 255,
            ],
            'city' => [
                'required' => true,
                'type' => 'string',
                'title' => 'Ville',
                'maxLength' => 155,
            ],
            'region' => [
                'required' => true,
                'type' => 'string',
                'title' => 'Région',
                'values' => Department::getFrenchRegions(),
                'maxLength' => 155,
            ],
            'postalCode' => [
                'required' => true,
                'type' => 'integer',
                'title' => 'Code postal',
                'maxLength' => 10,
            ],
            'country' => [
                'required' => true,
                'type' => 'string',
                'title' => 'Pays',
                'maxLength' => 2,
            ],
            'maritalStatus' => [
                'required' => true,
                'type' => 'integer',
                'title' => 'Situation maritale',
                'values' => $this->getMaritalStatuses(),
                'maxLength' => 2,
            ],
            'profession' => [
                'required' => true,
                'type' => 'integer',
                'title' => 'Situation professionnelle',
                'values' => $this->getProfessions(),
                'maxLength' => 2,
            ],
            'risk1' => [
                'required' => true,
                'type' => 'boolean',
                'title' => "L'investissement projeté peut représenter un risque de perte partielle voire totale du capital, en êtes-vous conscient ?",
                'values' => [
                    'Non' => false,
                    'Oui' => true,
                ],
                'maxLength' => 1,
            ],
            'risk2' => [
                'required' => true,
                'type' => 'boolean',
                'title' => "L'investissement projeté peut présenter un risque d'illiquidité qui peut vous empêcher de revendre vos titres au moment souhaité, en êtes-vous conscient ?",
                'values' => [
                    'Non' => false,
                    'Oui' => true,
                ],
                'maxLength' => 1,
            ],
        ];
    }
}
