<?php

namespace App\Controller\Api;

use App\Services\AddressApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/address')]
class AddressController extends AbstractController
{
    public function __construct(
        private AddressApiService $addressApiService
    ) {}

    #[Route('/search', name: 'api_address_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 5);

        if (strlen($query) < 3) {
            return new JsonResponse([
                'success' => false,
                'message' => 'La requête doit contenir au moins 3 caractères'
            ]);
        }

        try {
            $addresses = $this->addressApiService->searchAddresses($query, $limit);
            
            return new JsonResponse([
                'success' => true,
                'data' => $addresses
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la recherche d\'adresses'
            ]);
        }
    }

    #[Route('/communes/name', name: 'api_address_communes_by_name', methods: ['GET'])]
    public function searchCommunesByName(Request $request): JsonResponse
    {
        $name = $request->query->get('name', '');
        $limit = (int) $request->query->get('limit', 5);

        if (strlen($name) < 2) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le nom doit contenir au moins 2 caractères'
            ]);
        }

        try {
            $cities = $this->addressApiService->searchCommunesByName($name, $limit);
            
            return new JsonResponse([
                'success' => true,
                'data' => $cities
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la recherche de villes'
            ]);
        }
    }

    #[Route('/communes/postal-code', name: 'api_address_communes_by_postal_code', methods: ['GET'])]
    public function searchCommunesByPostalCode(Request $request): JsonResponse
    {
        $postalCode = $request->query->get('codePostal', '');

        if (empty($postalCode)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Code postal requis'
            ]);
        }

        try {
            $cities = $this->addressApiService->searchCommunesByPostalCode($postalCode);
            
            return new JsonResponse([
                'success' => true,
                'data' => $cities
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la recherche de villes'
            ]);
        }
    }
} 