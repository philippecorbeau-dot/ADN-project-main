<?php

namespace App\Services;

use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

class PhoneNumberService
{
    private PhoneNumberUtil $phoneNumberUtil;

    public function __construct()
    {
        $this->phoneNumberUtil = PhoneNumberUtil::getInstance();
    }

    /**
     * Récupère tous les pays avec leurs indicatifs
     */
    public function getCountriesWithCodes(): array
    {
        $countries = [];
        
        // Liste des pays principaux avec leurs indicatifs et drapeaux
        $mainCountries = [
            'FR' => ['name' => 'France', 'dialCode' => '+33', 'flag' => '🇫🇷'],
            'BE' => ['name' => 'Belgique', 'dialCode' => '+32', 'flag' => '🇧🇪'],
            'CH' => ['name' => 'Suisse', 'dialCode' => '+41', 'flag' => '🇨🇭'],
            'CA' => ['name' => 'Canada', 'dialCode' => '+1', 'flag' => '🇨🇦'],
            'US' => ['name' => 'États-Unis', 'dialCode' => '+1', 'flag' => '🇺🇸'],
            'GB' => ['name' => 'Royaume-Uni', 'dialCode' => '+44', 'flag' => '🇬🇧'],
            'DE' => ['name' => 'Allemagne', 'dialCode' => '+49', 'flag' => '🇩🇪'],
            'IT' => ['name' => 'Italie', 'dialCode' => '+39', 'flag' => '🇮🇹'],
            'ES' => ['name' => 'Espagne', 'dialCode' => '+34', 'flag' => '🇪🇸'],
            'NL' => ['name' => 'Pays-Bas', 'dialCode' => '+31', 'flag' => '🇳🇱'],
            'PT' => ['name' => 'Portugal', 'dialCode' => '+351', 'flag' => '🇵🇹'],
            'AT' => ['name' => 'Autriche', 'dialCode' => '+43', 'flag' => '🇦🇹'],
            'SE' => ['name' => 'Suède', 'dialCode' => '+46', 'flag' => '🇸🇪'],
            'NO' => ['name' => 'Norvège', 'dialCode' => '+47', 'flag' => '🇳🇴'],
            'DK' => ['name' => 'Danemark', 'dialCode' => '+45', 'flag' => '🇩🇰'],
            'FI' => ['name' => 'Finlande', 'dialCode' => '+358', 'flag' => '🇫🇮'],
            'PL' => ['name' => 'Pologne', 'dialCode' => '+48', 'flag' => '🇵🇱'],
            'CZ' => ['name' => 'République tchèque', 'dialCode' => '+420', 'flag' => '🇨🇿'],
            'HU' => ['name' => 'Hongrie', 'dialCode' => '+36', 'flag' => '🇭🇺'],
            'RO' => ['name' => 'Roumanie', 'dialCode' => '+40', 'flag' => '🇷🇴'],
            'BG' => ['name' => 'Bulgarie', 'dialCode' => '+359', 'flag' => '🇧🇬'],
            'HR' => ['name' => 'Croatie', 'dialCode' => '+385', 'flag' => '🇭🇷'],
            'SI' => ['name' => 'Slovénie', 'dialCode' => '+386', 'flag' => '🇸🇮'],
            'SK' => ['name' => 'Slovaquie', 'dialCode' => '+421', 'flag' => '🇸🇰'],
            'LT' => ['name' => 'Lituanie', 'dialCode' => '+370', 'flag' => '🇱🇹'],
            'LV' => ['name' => 'Lettonie', 'dialCode' => '+371', 'flag' => '🇱🇻'],
            'EE' => ['name' => 'Estonie', 'dialCode' => '+372', 'flag' => '🇪🇪'],
            'IE' => ['name' => 'Irlande', 'dialCode' => '+353', 'flag' => '🇮🇪'],
            'LU' => ['name' => 'Luxembourg', 'dialCode' => '+352', 'flag' => '🇱🇺'],
            'MT' => ['name' => 'Malte', 'dialCode' => '+356', 'flag' => '🇲🇹'],
            'CY' => ['name' => 'Chypre', 'dialCode' => '+357', 'flag' => '🇨🇾'],
            'GR' => ['name' => 'Grèce', 'dialCode' => '+30', 'flag' => '🇬🇷'],
            'JP' => ['name' => 'Japon', 'dialCode' => '+81', 'flag' => '🇯🇵'],
            'KR' => ['name' => 'Corée du Sud', 'dialCode' => '+82', 'flag' => '🇰🇷'],
            'CN' => ['name' => 'Chine', 'dialCode' => '+86', 'flag' => '🇨🇳'],
            'IN' => ['name' => 'Inde', 'dialCode' => '+91', 'flag' => '🇮🇳'],
            'BR' => ['name' => 'Brésil', 'dialCode' => '+55', 'flag' => '🇧🇷'],
            'MX' => ['name' => 'Mexique', 'dialCode' => '+52', 'flag' => '🇲🇽'],
            'AR' => ['name' => 'Argentine', 'dialCode' => '+54', 'flag' => '🇦🇷'],
            'CL' => ['name' => 'Chili', 'dialCode' => '+56', 'flag' => '🇨🇱'],
            'CO' => ['name' => 'Colombie', 'dialCode' => '+57', 'flag' => '🇨🇴'],
            'PE' => ['name' => 'Pérou', 'dialCode' => '+51', 'flag' => '🇵🇪'],
            'VE' => ['name' => 'Venezuela', 'dialCode' => '+58', 'flag' => '🇻🇪'],
            'UY' => ['name' => 'Uruguay', 'dialCode' => '+598', 'flag' => '🇺🇾'],
            'PY' => ['name' => 'Paraguay', 'dialCode' => '+595', 'flag' => '🇵🇾'],
            'BO' => ['name' => 'Bolivie', 'dialCode' => '+591', 'flag' => '🇧🇴'],
            'EC' => ['name' => 'Équateur', 'dialCode' => '+593', 'flag' => '🇪🇨'],
            'AU' => ['name' => 'Australie', 'dialCode' => '+61', 'flag' => '🇦🇺'],
            'NZ' => ['name' => 'Nouvelle-Zélande', 'dialCode' => '+64', 'flag' => '🇳🇿'],
            'ZA' => ['name' => 'Afrique du Sud', 'dialCode' => '+27', 'flag' => '🇿🇦'],
            'EG' => ['name' => 'Égypte', 'dialCode' => '+20', 'flag' => '🇪🇬'],
            'MA' => ['name' => 'Maroc', 'dialCode' => '+212', 'flag' => '🇲🇦'],
            'TN' => ['name' => 'Tunisie', 'dialCode' => '+216', 'flag' => '🇹🇳'],
            'DZ' => ['name' => 'Algérie', 'dialCode' => '+213', 'flag' => '🇩🇿'],
            'SN' => ['name' => 'Sénégal', 'dialCode' => '+221', 'flag' => '🇸🇳'],
            'CI' => ['name' => 'Côte d\'Ivoire', 'dialCode' => '+225', 'flag' => '🇨🇮'],
            'CM' => ['name' => 'Cameroun', 'dialCode' => '+237', 'flag' => '🇨🇲'],
            'NG' => ['name' => 'Nigeria', 'dialCode' => '+234', 'flag' => '🇳🇬'],
            'KE' => ['name' => 'Kenya', 'dialCode' => '+254', 'flag' => '🇰🇪'],
            'GH' => ['name' => 'Ghana', 'dialCode' => '+233', 'flag' => '🇬🇭'],
            'UG' => ['name' => 'Ouganda', 'dialCode' => '+256', 'flag' => '🇺🇬'],
            'TZ' => ['name' => 'Tanzanie', 'dialCode' => '+255', 'flag' => '🇹🇿'],
            'ET' => ['name' => 'Éthiopie', 'dialCode' => '+251', 'flag' => '🇪🇹'],
            'SD' => ['name' => 'Soudan', 'dialCode' => '+249', 'flag' => '🇸🇩'],
            'LY' => ['name' => 'Libye', 'dialCode' => '+218', 'flag' => '🇱🇾'],
            'IL' => ['name' => 'Israël', 'dialCode' => '+972', 'flag' => '🇮🇱'],
            'SA' => ['name' => 'Arabie saoudite', 'dialCode' => '+966', 'flag' => '🇸🇦'],
            'AE' => ['name' => 'Émirats arabes unis', 'dialCode' => '+971', 'flag' => '🇦🇪'],
            'QA' => ['name' => 'Qatar', 'dialCode' => '+974', 'flag' => '🇶🇦'],
            'KW' => ['name' => 'Koweït', 'dialCode' => '+965', 'flag' => '🇰🇼'],
            'BH' => ['name' => 'Bahreïn', 'dialCode' => '+973', 'flag' => '🇧🇭'],
            'OM' => ['name' => 'Oman', 'dialCode' => '+968', 'flag' => '🇴🇲'],
            'JO' => ['name' => 'Jordanie', 'dialCode' => '+962', 'flag' => '🇯🇴'],
            'LB' => ['name' => 'Liban', 'dialCode' => '+961', 'flag' => '🇱🇧'],
            'SY' => ['name' => 'Syrie', 'dialCode' => '+963', 'flag' => '🇸🇾'],
            'IQ' => ['name' => 'Irak', 'dialCode' => '+964', 'flag' => '🇮🇶'],
            'IR' => ['name' => 'Iran', 'dialCode' => '+98', 'flag' => '🇮🇷'],
            'TR' => ['name' => 'Turquie', 'dialCode' => '+90', 'flag' => '🇹🇷'],
            'RU' => ['name' => 'Russie', 'dialCode' => '+7', 'flag' => '🇷🇺'],
            'UA' => ['name' => 'Ukraine', 'dialCode' => '+380', 'flag' => '🇺🇦'],
            'BY' => ['name' => 'Biélorussie', 'dialCode' => '+375', 'flag' => '🇧🇾'],
            'MD' => ['name' => 'Moldavie', 'dialCode' => '+373', 'flag' => '🇲🇩'],
            'GE' => ['name' => 'Géorgie', 'dialCode' => '+995', 'flag' => '🇬🇪'],
            'AM' => ['name' => 'Arménie', 'dialCode' => '+374', 'flag' => '🇦🇲'],
            'AZ' => ['name' => 'Azerbaïdjan', 'dialCode' => '+994', 'flag' => '🇦🇿'],
            'KZ' => ['name' => 'Kazakhstan', 'dialCode' => '+7', 'flag' => '🇰🇿'],
            'UZ' => ['name' => 'Ouzbékistan', 'dialCode' => '+998', 'flag' => '🇺🇿'],
            'KG' => ['name' => 'Kirghizistan', 'dialCode' => '+996', 'flag' => '🇰🇬'],
            'TJ' => ['name' => 'Tadjikistan', 'dialCode' => '+992', 'flag' => '🇹🇯'],
            'TM' => ['name' => 'Turkménistan', 'dialCode' => '+993', 'flag' => '🇹🇲'],
            'AF' => ['name' => 'Afghanistan', 'dialCode' => '+93', 'flag' => '🇦🇫'],
            'PK' => ['name' => 'Pakistan', 'dialCode' => '+92', 'flag' => '🇵🇰'],
            'BD' => ['name' => 'Bangladesh', 'dialCode' => '+880', 'flag' => '🇧🇩'],
            'LK' => ['name' => 'Sri Lanka', 'dialCode' => '+94', 'flag' => '🇱🇰'],
            'NP' => ['name' => 'Népal', 'dialCode' => '+977', 'flag' => '🇳🇵'],
            'BT' => ['name' => 'Bhoutan', 'dialCode' => '+975', 'flag' => '🇧🇹'],
            'MV' => ['name' => 'Maldives', 'dialCode' => '+960', 'flag' => '🇲🇻'],
            'TH' => ['name' => 'Thaïlande', 'dialCode' => '+66', 'flag' => '🇹🇭'],
            'VN' => ['name' => 'Vietnam', 'dialCode' => '+84', 'flag' => '🇻🇳'],
            'LA' => ['name' => 'Laos', 'dialCode' => '+856', 'flag' => '🇱🇦'],
            'KH' => ['name' => 'Cambodge', 'dialCode' => '+855', 'flag' => '🇰🇭'],
            'MM' => ['name' => 'Myanmar', 'dialCode' => '+95', 'flag' => '🇲🇲'],
            'MY' => ['name' => 'Malaisie', 'dialCode' => '+60', 'flag' => '🇲🇾'],
            'SG' => ['name' => 'Singapour', 'dialCode' => '+65', 'flag' => '🇸🇬'],
            'ID' => ['name' => 'Indonésie', 'dialCode' => '+62', 'flag' => '🇮🇩'],
            'PH' => ['name' => 'Philippines', 'dialCode' => '+63', 'flag' => '🇵🇭'],
            'BN' => ['name' => 'Brunei', 'dialCode' => '+673', 'flag' => '🇧🇳'],
            'TL' => ['name' => 'Timor oriental', 'dialCode' => '+670', 'flag' => '🇹🇱'],
            'PG' => ['name' => 'Papouasie-Nouvelle-Guinée', 'dialCode' => '+675', 'flag' => '🇵🇬'],
            'FJ' => ['name' => 'Fidji', 'dialCode' => '+679', 'flag' => '🇫🇯'],
            'NC' => ['name' => 'Nouvelle-Calédonie', 'dialCode' => '+687', 'flag' => '🇳🇨'],
            'PF' => ['name' => 'Polynésie française', 'dialCode' => '+689', 'flag' => '🇵🇫'],
            'VU' => ['name' => 'Vanuatu', 'dialCode' => '+678', 'flag' => '🇻🇺'],
            'SB' => ['name' => 'Îles Salomon', 'dialCode' => '+677', 'flag' => '🇸🇧'],
            'TO' => ['name' => 'Tonga', 'dialCode' => '+676', 'flag' => '🇹🇴'],
            'WS' => ['name' => 'Samoa', 'dialCode' => '+685', 'flag' => '🇼🇸'],
            'KI' => ['name' => 'Kiribati', 'dialCode' => '+686', 'flag' => '🇰🇮'],
            'TV' => ['name' => 'Tuvalu', 'dialCode' => '+688', 'flag' => '🇹🇻'],
            'NR' => ['name' => 'Nauru', 'dialCode' => '+674', 'flag' => '🇳🇷'],
            'PW' => ['name' => 'Palaos', 'dialCode' => '+680', 'flag' => '🇵🇼'],
            'MH' => ['name' => 'Îles Marshall', 'dialCode' => '+692', 'flag' => '🇲🇭'],
            'FM' => ['name' => 'Micronésie', 'dialCode' => '+691', 'flag' => '🇫🇲'],
            'CK' => ['name' => 'Îles Cook', 'dialCode' => '+682', 'flag' => '🇨🇰'],
            'NU' => ['name' => 'Niue', 'dialCode' => '+683', 'flag' => '🇳🇺'],
            'TK' => ['name' => 'Tokelau', 'dialCode' => '+690', 'flag' => '🇹🇰'],
            'WF' => ['name' => 'Wallis-et-Futuna', 'dialCode' => '+681', 'flag' => '🇼🇫'],
            'AS' => ['name' => 'Samoa américaines', 'dialCode' => '+1', 'flag' => '🇦🇸'],
            'GU' => ['name' => 'Guam', 'dialCode' => '+1', 'flag' => '🇬🇺'],
            'MP' => ['name' => 'Îles Mariannes du Nord', 'dialCode' => '+1', 'flag' => '🇲🇵'],
            'PR' => ['name' => 'Porto Rico', 'dialCode' => '+1', 'flag' => '🇵🇷'],
            'VI' => ['name' => 'Îles Vierges américaines', 'dialCode' => '+1', 'flag' => '🇻🇮'],
            'AI' => ['name' => 'Anguilla', 'dialCode' => '+1', 'flag' => '🇦🇮'],
            'AG' => ['name' => 'Antigua-et-Barbuda', 'dialCode' => '+1', 'flag' => '🇦🇬'],
            'AW' => ['name' => 'Aruba', 'dialCode' => '+297', 'flag' => '🇦🇼'],
            'BS' => ['name' => 'Bahamas', 'dialCode' => '+1', 'flag' => '🇧🇸'],
            'BB' => ['name' => 'Barbade', 'dialCode' => '+1', 'flag' => '🇧🇧'],
            'BZ' => ['name' => 'Belize', 'dialCode' => '+501', 'flag' => '🇧🇿'],
            'BM' => ['name' => 'Bermudes', 'dialCode' => '+1', 'flag' => '🇧🇲'],
            'VG' => ['name' => 'Îles Vierges britanniques', 'dialCode' => '+1', 'flag' => '🇻🇬'],
            'KY' => ['name' => 'Îles Caïmans', 'dialCode' => '+1', 'flag' => '🇰🇾'],
            'CR' => ['name' => 'Costa Rica', 'dialCode' => '+506', 'flag' => '🇨🇷'],
            'CU' => ['name' => 'Cuba', 'dialCode' => '+53', 'flag' => '🇨🇺'],
            'DM' => ['name' => 'Dominique', 'dialCode' => '+1', 'flag' => '🇩🇲'],
            'DO' => ['name' => 'République dominicaine', 'dialCode' => '+1', 'flag' => '🇩🇴'],
            'SV' => ['name' => 'El Salvador', 'dialCode' => '+503', 'flag' => '🇸🇻'],
            'GD' => ['name' => 'Grenade', 'dialCode' => '+1', 'flag' => '🇬🇩'],
            'GT' => ['name' => 'Guatemala', 'dialCode' => '+502', 'flag' => '🇬🇹'],
            'HT' => ['name' => 'Haïti', 'dialCode' => '+509', 'flag' => '🇭🇹'],
            'HN' => ['name' => 'Honduras', 'dialCode' => '+504', 'flag' => '🇭🇳'],
            'JM' => ['name' => 'Jamaïque', 'dialCode' => '+1', 'flag' => '🇯🇲'],
            'NI' => ['name' => 'Nicaragua', 'dialCode' => '+505', 'flag' => '🇳🇮'],
            'PA' => ['name' => 'Panama', 'dialCode' => '+507', 'flag' => '🇵🇦'],
            'KN' => ['name' => 'Saint-Kitts-et-Nevis', 'dialCode' => '+1', 'flag' => '🇰🇳'],
            'LC' => ['name' => 'Sainte-Lucie', 'dialCode' => '+1', 'flag' => '🇱🇨'],
            'VC' => ['name' => 'Saint-Vincent-et-les-Grenadines', 'dialCode' => '+1', 'flag' => '🇻🇨'],
            'TT' => ['name' => 'Trinité-et-Tobago', 'dialCode' => '+1', 'flag' => '🇹🇹'],
            'TC' => ['name' => 'Îles Turques-et-Caïques', 'dialCode' => '+1', 'flag' => '🇹🇨'],
            'GP' => ['name' => 'Guadeloupe', 'dialCode' => '+590', 'flag' => '🇬🇵'],
            'MQ' => ['name' => 'Martinique', 'dialCode' => '+596', 'flag' => '🇲🇶'],
            'GF' => ['name' => 'Guyane française', 'dialCode' => '+594', 'flag' => '🇬🇫'],
            'RE' => ['name' => 'Réunion', 'dialCode' => '+262', 'flag' => '🇷🇪'],
            'YT' => ['name' => 'Mayotte', 'dialCode' => '+262', 'flag' => '🇾🇹'],
            'BL' => ['name' => 'Saint-Barthélemy', 'dialCode' => '+590', 'flag' => '🇧🇱'],
            'MF' => ['name' => 'Saint-Martin', 'dialCode' => '+590', 'flag' => '🇲🇫'],
            'PM' => ['name' => 'Saint-Pierre-et-Miquelon', 'dialCode' => '+508', 'flag' => '🇵🇲'],
            'TF' => ['name' => 'Terres australes françaises', 'dialCode' => '+262', 'flag' => '🇹🇫'],
            'AD' => ['name' => 'Andorre', 'dialCode' => '+376', 'flag' => '🇦🇩'],
            'MC' => ['name' => 'Monaco', 'dialCode' => '+377', 'flag' => '🇲🇨'],
            'LI' => ['name' => 'Liechtenstein', 'dialCode' => '+423', 'flag' => '🇱🇮'],
            'SM' => ['name' => 'Saint-Marin', 'dialCode' => '+378', 'flag' => '🇸🇲'],
            'VA' => ['name' => 'Vatican', 'dialCode' => '+379', 'flag' => '🇻🇦'],
            'IS' => ['name' => 'Islande', 'dialCode' => '+354', 'flag' => '🇮🇸'],
            'FO' => ['name' => 'Îles Féroé', 'dialCode' => '+298', 'flag' => '🇫🇴'],
            'GL' => ['name' => 'Groenland', 'dialCode' => '+299', 'flag' => '🇬🇱'],
            'SJ' => ['name' => 'Svalbard et Jan Mayen', 'dialCode' => '+47', 'flag' => '🇸🇯'],
            'AX' => ['name' => 'Îles Åland', 'dialCode' => '+358', 'flag' => '🇦🇽'],
            'GI' => ['name' => 'Gibraltar', 'dialCode' => '+350', 'flag' => '🇬🇮'],
        ];

        foreach ($mainCountries as $code => $country) {
            $countries[] = [
                'code' => $code,
                'name' => $country['name'],
                'dialCode' => $country['dialCode'],
                'flag' => $country['flag'],
                'example' => $this->getExampleNumber($code)
            ];
        }

        // Trier par nom de pays
        usort($countries, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $countries;
    }

    /**
     * Génère un exemple de numéro pour un pays
     */
    private function getExampleNumber(string $countryCode): string
    {
        try {
            $exampleNumber = $this->phoneNumberUtil->getExampleNumber($countryCode);
            if ($exampleNumber) {
                return $this->phoneNumberUtil->format($exampleNumber, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
            }
        } catch (\Exception $e) {
            // En cas d'erreur, retourner un exemple basique
        }

        // Exemples par défaut selon le pays
        $defaultExamples = [
            'FR' => '+33 6 12 34 56 78',
            'BE' => '+32 470 12 34 56',
            'CH' => '+41 76 123 45 67',
            'CA' => '+1 (514) 123-4567',
            'US' => '+1 (555) 123-4567',
            'GB' => '+44 7700 900123',
            'DE' => '+49 151 12345678',
            'IT' => '+39 312 345 6789',
            'ES' => '+34 612 345 678',
            'NL' => '+31 6 12345678',
            'PT' => '+351 912 345 678',
            'AT' => '+43 664 12345678',
            'SE' => '+46 70 123 45 67',
            'NO' => '+47 412 34 567',
            'DK' => '+45 12 34 56 78',
            'FI' => '+358 40 123 4567',
            'PL' => '+48 123 456 789',
            'CZ' => '+420 123 456 789',
            'HU' => '+36 20 123 4567',
            'RO' => '+40 723 456 789',
            'BG' => '+359 888 123 456',
            'HR' => '+385 91 123 4567',
            'SI' => '+386 31 123 456',
            'SK' => '+421 905 123 456',
            'LT' => '+370 612 34567',
            'LV' => '+371 21234567',
            'EE' => '+372 51234567',
            'IE' => '+353 87 123 4567',
            'LU' => '+352 621 123 456',
            'MT' => '+356 2123 4567',
            'CY' => '+357 99 123 456',
            'GR' => '+30 691 234 5678',
            'AD' => '+376 312 345',
            'MC' => '+377 6 12 34 56 78',
            'LI' => '+423 791 234 567',
            'SM' => '+378 66 123 456',
            'VA' => '+379 66 123 456',
            'IS' => '+354 660 1234',
            'FO' => '+298 123 456',
            'GL' => '+299 12 34 56',
            'SJ' => '+47 123 45 678',
            'AX' => '+358 18 123 4567',
            'GI' => '+350 571 234 56',
            'JP' => '+81 90 1234 5678',
            'KR' => '+82 10 1234 5678',
            'CN' => '+86 138 1234 5678',
            'IN' => '+91 98765 43210',
            'BR' => '+55 11 98765 4321',
            'MX' => '+52 1 55 1234 5678',
            'AR' => '+54 9 11 1234 5678',
            'CL' => '+56 9 1234 5678',
            'CO' => '+57 300 123 4567',
            'PE' => '+51 999 123 456',
            'VE' => '+58 412 123 4567',
            'UY' => '+598 99 123 456',
            'PY' => '+595 981 123 456',
            'BO' => '+591 712 345 67',
            'EC' => '+593 99 123 4567',
            'AU' => '+61 412 345 678',
            'NZ' => '+64 21 123 4567',
            'ZA' => '+27 82 123 4567',
            'EG' => '+20 10 1234 5678',
            'MA' => '+212 6 12 34 56 78',
            'TN' => '+216 20 123 456',
            'DZ' => '+213 5 12 34 56 78',
            'SN' => '+221 77 123 45 67',
            'CI' => '+225 07 12 34 56 78',
            'CM' => '+237 6 12 34 56 78',
            'NG' => '+234 801 234 5678',
            'KE' => '+254 712 345 678',
            'GH' => '+233 20 123 4567',
            'UG' => '+256 712 345 678',
            'TZ' => '+255 712 345 678',
            'ET' => '+251 911 234 567',
            'SD' => '+249 912 345 678',
            'LY' => '+218 91 234 5678',
            'IL' => '+972 50 123 4567',
            'SA' => '+966 50 123 4567',
            'AE' => '+971 50 123 4567',
            'QA' => '+974 3012 3456',
            'KW' => '+965 500 123 45',
            'BH' => '+973 3000 1234',
            'OM' => '+968 9123 4567',
            'JO' => '+962 79 123 4567',
            'LB' => '+961 3 123 456',
            'SY' => '+963 944 123 456',
            'IQ' => '+964 750 123 4567',
            'IR' => '+98 912 123 4567',
            'TR' => '+90 532 123 4567',
            'RU' => '+7 916 123 4567',
            'UA' => '+380 50 123 4567',
            'BY' => '+375 29 123 4567',
            'MD' => '+373 601 123 45',
            'GE' => '+995 599 123 456',
            'AM' => '+374 91 123 456',
            'AZ' => '+994 50 123 4567',
            'KZ' => '+7 701 123 4567',
            'UZ' => '+998 90 123 4567',
            'KG' => '+996 700 123 456',
            'TJ' => '+992 918 123 456',
            'TM' => '+993 65 123 456',
            'AF' => '+93 70 123 4567',
            'PK' => '+92 300 123 4567',
            'BD' => '+880 18 1234 5678',
            'LK' => '+94 71 123 4567',
            'NP' => '+977 984 123 4567',
            'BT' => '+975 17 123 456',
            'MV' => '+960 777 1234',
            'TH' => '+66 81 234 5678',
            'VN' => '+84 90 123 4567',
            'LA' => '+856 20 123 45678',
            'KH' => '+855 12 123 456',
            'MM' => '+95 9 123 456 789',
            'MY' => '+60 12 345 6789',
            'SG' => '+65 8123 4567',
            'ID' => '+62 812 345 67890',
            'PH' => '+63 912 345 6789',
            'BN' => '+673 712 3456',
            'TL' => '+670 772 12345',
            'PG' => '+675 712 345 67',
            'FJ' => '+679 712 3456',
            'NC' => '+687 123 456',
            'PF' => '+689 87 12 34 56',
            'VU' => '+678 123 4567',
            'SB' => '+677 123 4567',
            'TO' => '+676 123 4567',
            'WS' => '+685 123 4567',
            'KI' => '+686 123 4567',
            'TV' => '+688 123 4567',
            'NR' => '+674 123 4567',
            'PW' => '+680 123 4567',
            'MH' => '+692 123 4567',
            'FM' => '+691 123 4567',
            'CK' => '+682 123 4567',
            'NU' => '+683 123 4567',
            'TK' => '+690 123 4567',
            'WF' => '+681 123 4567',
            'AS' => '+1 684 123 4567',
            'GU' => '+1 671 123 4567',
            'MP' => '+1 670 123 4567',
            'PR' => '+1 787 123 4567',
            'VI' => '+1 340 123 4567',
            'AI' => '+1 264 123 4567',
            'AG' => '+1 268 123 4567',
            'AW' => '+297 123 4567',
            'BS' => '+1 242 123 4567',
            'BB' => '+1 246 123 4567',
            'BZ' => '+501 123 4567',
            'BM' => '+1 441 123 4567',
            'VG' => '+1 284 123 4567',
            'KY' => '+1 345 123 4567',
            'CR' => '+506 1234 5678',
            'CU' => '+53 5 123 4567',
            'DM' => '+1 767 123 4567',
            'DO' => '+1 809 123 4567',
            'SV' => '+503 1234 5678',
            'GD' => '+1 473 123 4567',
            'GT' => '+502 1234 5678',
            'HT' => '+509 1234 5678',
            'HN' => '+504 1234 5678',
            'JM' => '+1 876 123 4567',
            'NI' => '+505 1234 5678',
            'PA' => '+507 1234 5678',
            'KN' => '+1 869 123 4567',
            'LC' => '+1 758 123 4567',
            'VC' => '+1 784 123 4567',
            'TT' => '+1 868 123 4567',
            'TC' => '+1 649 123 4567',
            'GP' => '+590 690 12 34 56',
            'MQ' => '+596 696 12 34 56',
            'GF' => '+594 694 12 34 56',
            'RE' => '+262 692 12 34 56',
            'YT' => '+262 639 12 34 56',
            'BL' => '+590 690 12 34 56',
            'MF' => '+590 690 12 34 56',
            'PM' => '+508 41 12 34 56',
            'TF' => '+262 262 12 34 56',
        ];

        return $defaultExamples[$countryCode] ?? '+33 6 12 34 56 78';
    }

    /**
     * Détecte le pays à partir d'un numéro de téléphone
     */
    public function detectCountryFromNumber(string $phoneNumber): ?array
    {
        try {
            $number = $this->phoneNumberUtil->parse($phoneNumber, null);
            $region = $this->phoneNumberUtil->getRegionCodeForNumber($number);
            
            if ($region) {
                // Récupérer les informations du pays avec le drapeau
                $countries = $this->getCountriesWithCodes();
                foreach ($countries as $country) {
                    if ($country['code'] === $region) {
                        return [
                            'code' => $region,
                            'name' => $country['name'],
                            'dialCode' => '+' . $number->getCountryCode(),
                            'flag' => $country['flag']
                        ];
                    }
                }
                
                // Fallback si le pays n'est pas dans notre liste
                return [
                    'code' => $region,
                    'name' => \Locale::getDisplayRegion('-' . $region, 'fr'),
                    'dialCode' => '+' . $number->getCountryCode(),
                    'flag' => '🏳️' // Drapeau neutre par défaut
                ];
            }
        } catch (NumberParseException $e) {
            // Numéro invalide, essayer de détecter manuellement
            return $this->detectCountryManually($phoneNumber);
        }

        return null;
    }

    /**
     * Détection manuelle pour les numéros sans format international
     */
    private function detectCountryManually(string $phoneNumber): ?array
    {
        // Nettoyer le numéro
        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Détection basée sur les patterns
        if (strlen($cleanNumber) >= 4 && $cleanNumber[0] === '0') {
            // Numéro qui commence par 0 - détecter le pays selon la longueur
            if (strlen($cleanNumber) === 9) {
                // Numéro de 9 chiffres - probablement belge ou suisse
                // Par défaut, on considère que c'est belge
                return [
                    'code' => 'BE',
                    'name' => 'Belgique',
                    'dialCode' => '+32',
                    'flag' => '🇧🇪'
                ];
            } else if (strlen($cleanNumber) === 10) {
                // Numéro de 10 chiffres - vérifier le préfixe
                $prefix = substr($cleanNumber, 0, 2);
                if ($prefix === '04') {
                    // Numéro belge (commence par 04)
                    return [
                        'code' => 'BE',
                        'name' => 'Belgique',
                        'dialCode' => '+32',
                        'flag' => '🇧🇪'
                    ];
                } else if ($prefix === '07') {
                    // Numéro suisse (commence par 07)
                    return [
                        'code' => 'CH',
                        'name' => 'Suisse',
                        'dialCode' => '+41',
                        'flag' => '🇨🇭'
                    ];
                } else {
                    // Numéro français (autres préfixes)
                    return [
                        'code' => 'FR',
                        'name' => 'France',
                        'dialCode' => '+33',
                        'flag' => '🇫🇷'
                    ];
                }
            } else {
                // Numéro français (autres longueurs)
                return [
                    'code' => 'FR',
                    'name' => 'France',
                    'dialCode' => '+33',
                    'flag' => '🇫🇷'
                ];
            }
        }
        
        if (strlen($cleanNumber) >= 4 && $cleanNumber[0] === '1') {
            // Numéro américain/canadien (commence par 1)
            return [
                'code' => 'US',
                'name' => 'États-Unis',
                'dialCode' => '+1',
                'flag' => '🇺🇸'
            ];
        }
        
        if (strlen($cleanNumber) >= 4 && $cleanNumber[0] === '4') {
            // Numéro britannique (commence par 4)
            return [
                'code' => 'GB',
                'name' => 'Royaume-Uni',
                'dialCode' => '+44',
                'flag' => '🇬🇧'
            ];
        }
        
        return null;
    }

    /**
     * Formate un numéro de téléphone
     */
    public function formatPhoneNumber(string $phoneNumber, string $region = 'FR'): string
    {
        try {
            $number = $this->phoneNumberUtil->parse($phoneNumber, $region);
            return $this->phoneNumberUtil->format($number, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);
        } catch (NumberParseException $e) {
            return $phoneNumber;
        }
    }

    /**
     * Valide un numéro de téléphone
     */
    public function isValidPhoneNumber(string $phoneNumber, string $region = 'FR'): bool
    {
        try {
            $number = $this->phoneNumberUtil->parse($phoneNumber, $region);
            return $this->phoneNumberUtil->isValidNumber($number);
        } catch (NumberParseException $e) {
            return false;
        }
    }
} 