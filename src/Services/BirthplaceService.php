<?php

namespace App\Services;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class BirthplaceService
{
    private const FRENCH_API_URL = 'https://geo.api.gouv.fr/communes';
    private const NOMINATIM_API_URL = 'https://nominatim.openstreetmap.org/search';

    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    /**
     * Recherche de villes de naissance (françaises et étrangères)
     */
    public function searchBirthplaces(string $query, string $country = 'FR', int $limit = 10): array
    {
        if ($country === 'FR') {
            return $this->searchFrenchCities($query, $limit);
        } else {
            return $this->searchWorldCities($query, $country, $limit);
        }
    }

    /**
     * Recherche de villes françaises via l'API gouvernementale
     */
    private function searchFrenchCities(string $query, int $limit = 10): array
    {
        try {
            $response = $this->httpClient->request('GET', self::FRENCH_API_URL, [
                'query' => [
                    'nom' => $query,
                    'limit' => $limit,
                    'fields' => 'nom,code,codesPostaux,population'
                ]
            ]);

            $data = $response->toArray();
            
            return array_map(function($commune) {
                return [
                    'name' => $commune['nom'],
                    'country' => 'FR',
                    'countryName' => 'France',
                    'postalCodes' => $commune['codesPostaux'] ?? [],
                    'population' => $commune['population'] ?? null,
                    'type' => 'french_city',
                    'label' => $commune['nom'] . ' (' . implode(', ', $commune['codesPostaux'] ?? []) . ')'
                ];
            }, $data);

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Recherche de villes mondiales via OpenStreetMap/Nominatim
     */
    private function searchWorldCities(string $query, string $country, int $limit = 10): array
    {
        try {
            $response = $this->httpClient->request('GET', self::NOMINATIM_API_URL, [
                'query' => [
                    'q' => $query,
                    'countrycodes' => strtolower($country),
                    'format' => 'json',
                    'limit' => $limit,
                    'addressdetails' => 1,
                    'accept-language' => 'fr'
                ],
                'headers' => [
                    'User-Agent' => 'ADN-Family-Office/1.0'
                ]
            ]);

            $data = $response->toArray();
            
            return array_map(function($place) use ($country) {
                $address = $place['address'] ?? [];
                
                return [
                    'name' => $place['display_name'] ?? $place['name'] ?? '',
                    'country' => $country,
                    'countryName' => $this->getCountryName($country),
                    'postalCodes' => [],
                    'population' => null,
                    'type' => 'world_city',
                    'label' => $place['display_name'] ?? $place['name'] ?? '',
                    'coordinates' => [
                        'lat' => $place['lat'] ?? null,
                        'lon' => $place['lon'] ?? null
                    ]
                ];
            }, $data);

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtenir le nom du pays en français
     */
    private function getCountryName(string $countryCode): string
    {
        $countries = [
            'US' => 'États-Unis',
            'GB' => 'Royaume-Uni',
            'DE' => 'Allemagne',
            'IT' => 'Italie',
            'ES' => 'Espagne',
            'BE' => 'Belgique',
            'CH' => 'Suisse',
            'CA' => 'Canada',
            'AU' => 'Australie',
            'JP' => 'Japon',
            'CN' => 'Chine',
            'BR' => 'Brésil',
            'MX' => 'Mexique',
            'AR' => 'Argentine',
            'CL' => 'Chili',
            'CO' => 'Colombie',
            'PE' => 'Pérou',
            'VE' => 'Venezuela',
            'UY' => 'Uruguay',
            'PY' => 'Paraguay',
            'BO' => 'Bolivie',
            'EC' => 'Équateur',
            'MA' => 'Maroc',
            'TN' => 'Tunisie',
            'DZ' => 'Algérie',
            'SN' => 'Sénégal',
            'CI' => 'Côte d\'Ivoire',
            'CM' => 'Cameroun',
            'NG' => 'Nigeria',
            'KE' => 'Kenya',
            'GH' => 'Ghana',
            'UG' => 'Ouganda',
            'TZ' => 'Tanzanie',
            'ET' => 'Éthiopie',
            'SD' => 'Soudan',
            'LY' => 'Libye',
            'IL' => 'Israël',
            'SA' => 'Arabie saoudite',
            'AE' => 'Émirats arabes unis',
            'QA' => 'Qatar',
            'KW' => 'Koweït',
            'BH' => 'Bahreïn',
            'OM' => 'Oman',
            'JO' => 'Jordanie',
            'LB' => 'Liban',
            'SY' => 'Syrie',
            'IQ' => 'Irak',
            'IR' => 'Iran',
            'TR' => 'Turquie',
            'RU' => 'Russie',
            'UA' => 'Ukraine',
            'BY' => 'Biélorussie',
            'MD' => 'Moldavie',
            'GE' => 'Géorgie',
            'AM' => 'Arménie',
            'AZ' => 'Azerbaïdjan',
            'KZ' => 'Kazakhstan',
            'UZ' => 'Ouzbékistan',
            'KG' => 'Kirghizistan',
            'TJ' => 'Tadjikistan',
            'TM' => 'Turkménistan',
            'AF' => 'Afghanistan',
            'PK' => 'Pakistan',
            'BD' => 'Bangladesh',
            'LK' => 'Sri Lanka',
            'NP' => 'Népal',
            'BT' => 'Bhoutan',
            'MV' => 'Maldives',
            'TH' => 'Thaïlande',
            'VN' => 'Vietnam',
            'LA' => 'Laos',
            'KH' => 'Cambodge',
            'MM' => 'Myanmar',
            'MY' => 'Malaisie',
            'SG' => 'Singapour',
            'ID' => 'Indonésie',
            'PH' => 'Philippines',
            'BN' => 'Brunei',
            'TL' => 'Timor oriental',
            'PG' => 'Papouasie-Nouvelle-Guinée',
            'FJ' => 'Fidji',
            'NC' => 'Nouvelle-Calédonie',
            'PF' => 'Polynésie française',
            'VU' => 'Vanuatu',
            'SB' => 'Îles Salomon',
            'TO' => 'Tonga',
            'WS' => 'Samoa',
            'KI' => 'Kiribati',
            'TV' => 'Tuvalu',
            'NR' => 'Nauru',
            'PW' => 'Palaos',
            'MH' => 'Îles Marshall',
            'FM' => 'Micronésie',
            'CK' => 'Îles Cook',
            'NU' => 'Niue',
            'TK' => 'Tokelau',
            'WF' => 'Wallis-et-Futuna',
            'AS' => 'Samoa américaines',
            'GU' => 'Guam',
            'MP' => 'Îles Mariannes du Nord',
            'PR' => 'Porto Rico',
            'VI' => 'Îles Vierges américaines',
            'AI' => 'Anguilla',
            'AG' => 'Antigua-et-Barbuda',
            'AW' => 'Aruba',
            'BS' => 'Bahamas',
            'BB' => 'Barbade',
            'BZ' => 'Belize',
            'BM' => 'Bermudes',
            'VG' => 'Îles Vierges britanniques',
            'KY' => 'Îles Caïmans',
            'CR' => 'Costa Rica',
            'CU' => 'Cuba',
            'DM' => 'Dominique',
            'DO' => 'République dominicaine',
            'SV' => 'El Salvador',
            'GD' => 'Grenade',
            'GT' => 'Guatemala',
            'HT' => 'Haïti',
            'HN' => 'Honduras',
            'JM' => 'Jamaïque',
            'NI' => 'Nicaragua',
            'PA' => 'Panama',
            'KN' => 'Saint-Kitts-et-Nevis',
            'LC' => 'Sainte-Lucie',
            'VC' => 'Saint-Vincent-et-les-Grenadines',
            'TT' => 'Trinité-et-Tobago',
            'TC' => 'Îles Turques-et-Caïques',
            'GP' => 'Guadeloupe',
            'MQ' => 'Martinique',
            'GF' => 'Guyane française',
            'RE' => 'Réunion',
            'YT' => 'Mayotte',
            'BL' => 'Saint-Barthélemy',
            'MF' => 'Saint-Martin',
            'PM' => 'Saint-Pierre-et-Miquelon',
            'TF' => 'Terres australes françaises',
            'AD' => 'Andorre',
            'MC' => 'Monaco',
            'LI' => 'Liechtenstein',
            'SM' => 'Saint-Marin',
            'VA' => 'Vatican',
            'IS' => 'Islande',
            'FO' => 'Îles Féroé',
            'GL' => 'Groenland',
            'SJ' => 'Svalbard et Jan Mayen',
            'AX' => 'Îles Åland',
            'GI' => 'Gibraltar'
        ];

        return $countries[$countryCode] ?? $countryCode;
    }

    /**
     * Recherche de villes par pays sélectionné
     */
    public function searchByCountry(string $query, string $countryCode, int $limit = 10): array
    {
        return $this->searchBirthplaces($query, $countryCode, $limit);
    }
} 