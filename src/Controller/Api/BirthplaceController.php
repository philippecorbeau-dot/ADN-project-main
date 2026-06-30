<?php

namespace App\Controller\Api;

use App\Services\AddressApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/birthplace')]
class BirthplaceController extends AbstractController
{
    public function __construct(
        private AddressApiService $addressApiService
    ) {}

    #[Route('/search', name: 'api_birthplace_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $country = $request->query->get('country', 'FR');
        $limit = (int) $request->query->get('limit', 5);

        if (strlen($query) < 2) {
            return new JsonResponse([
                'success' => false,
                'message' => 'La requête doit contenir au moins 2 caractères'
            ]);
        }

        try {
            // Pour les villes françaises, utiliser l'API des communes
            if ($country === 'FR') {
                $cities = $this->addressApiService->searchCommunesByName($query, $limit);
                
                $formattedCities = array_map(function($city) {
                    return [
                        'name' => $city['nom'],
                        'code' => $city['code'],
                        'postalCodes' => $city['codesPostaux'],
                        'type' => 'french_city'
                    ];
                }, $cities);
                
                return new JsonResponse([
                    'success' => true,
                    'data' => $formattedCities
                ]);
            } else {
                // Pour les autres pays, retourner une liste basique
                return new JsonResponse([
                    'success' => true,
                    'data' => [
                        [
                            'name' => $query,
                            'code' => '',
                            'postalCodes' => [],
                            'type' => 'foreign_city'
                        ]
                    ]
                ]);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la recherche'
            ]);
        }
    }
} 