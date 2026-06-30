<?php

namespace App\Services;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class AddressApiService
{
    private const API_BASE_URL = 'https://api-adresse.data.gouv.fr/search/';
    private const API_POSTAL_URL = 'https://geo.api.gouv.fr/communes';

    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    /**
     * Recherche d'adresses via l'API gouvernementale
     */
    public function searchAddresses(string $query, int $limit = 10): array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_BASE_URL, [
                'query' => [
                    'q' => $query,
                    'limit' => $limit,
                    'autocomplete' => 1
                ]
            ]);

            $data = $response->toArray();
            
            return array_map(function($feature) {
                // Extraire le nom de rue et le numéro
                $street = $feature['properties']['name'] ?? '';
                $housenumber = $feature['properties']['housenumber'] ?? '';
                
                // Si le numéro est déjà dans le nom de la rue, l'extraire
                $streetName = $street;
                if ($housenumber && preg_match('/^' . preg_quote($housenumber, '/') . '\s+(.+)$/', $street, $matches)) {
                    $streetName = $matches[1];
                }
                
                // Construire l'adresse complète au format français : numéro + nom de rue
                $fullAddress = trim($housenumber . ' ' . $streetName);
                
                return [
                    'label' => $feature['properties']['label'],
                    'value' => $feature['properties']['label'],
                    'postcode' => $feature['properties']['postcode'],
                    'city' => $feature['properties']['city'],
                    'street' => $streetName,
                    'housenumber' => $housenumber,
                    'fullAddress' => $fullAddress,
                    'coordinates' => [
                        'lat' => $feature['geometry']['coordinates'][1],
                        'lng' => $feature['geometry']['coordinates'][0]
                    ]
                ];
            }, $data['features'] ?? []);

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Recherche de communes par code postal
     */
    public function searchCommunesByPostalCode(string $postalCode): array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_POSTAL_URL, [
                'query' => [
                    'codePostal' => $postalCode
                ]
            ]);

            $data = $response->toArray();
            
            return array_map(function($commune) {
                return [
                    'nom' => $commune['nom'],
                    'code' => $commune['code'],
                    'codesPostaux' => $commune['codesPostaux'],
                    'population' => $commune['population'] ?? null
                ];
            }, $data);

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Recherche de communes par nom
     */
    public function searchCommunesByName(string $name, int $limit = 10): array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_POSTAL_URL, [
                'query' => [
                    'nom' => $name,
                    'limit' => $limit
                ]
            ]);

            $data = $response->toArray();
            
            return array_map(function($commune) {
                return [
                    'nom' => $commune['nom'],
                    'code' => $commune['code'],
                    'codesPostaux' => $commune['codesPostaux'],
                    'population' => $commune['population'] ?? null
                ];
            }, $data);

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Recherche de codes postaux par commune
     */
    public function searchPostalCodesByCommune(string $communeName): array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_POSTAL_URL, [
                'query' => [
                    'nom' => $communeName
                ]
            ]);

            $data = $response->toArray();
            
            $postalCodes = [];
            foreach ($data as $commune) {
                $postalCodes = array_merge($postalCodes, $commune['codesPostaux']);
            }

            return array_unique($postalCodes);

        } catch (\Exception $e) {
            return [];
        }
    }
} 