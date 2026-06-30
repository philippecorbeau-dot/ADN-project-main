<?php

namespace App\Controller\Api;

use App\Services\PhoneNumberService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/phone', name: 'api_phone_')]
class PhoneController extends AbstractController
{
    public function __construct(
        private PhoneNumberService $phoneNumberService
    ) {}

    #[Route('/countries', name: 'countries', methods: ['GET'])]
    public function getCountries(): JsonResponse
    {
        $countries = $this->phoneNumberService->getCountriesWithCodes();
        
        return $this->json([
            'success' => true,
            'data' => $countries
        ]);
    }

    #[Route('/detect', name: 'detect', methods: ['POST'])]
    public function detectCountry(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $phoneNumber = $data['phone'] ?? '';

        if (empty($phoneNumber)) {
            return $this->json([
                'success' => false,
                'message' => 'Numéro de téléphone requis'
            ], 400);
        }

        $country = $this->phoneNumberService->detectCountryFromNumber($phoneNumber);
        
        return $this->json([
            'success' => true,
            'data' => $country
        ]);
    }

    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validatePhone(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $phoneNumber = $data['phone'] ?? '';
        $country = $data['country'] ?? 'FR';

        if (empty($phoneNumber)) {
            return $this->json([
                'success' => false,
                'message' => 'Numéro de téléphone requis'
            ], 400);
        }

        $isValid = $this->phoneNumberService->isValidPhoneNumber($phoneNumber, $country);
        $formatted = $this->phoneNumberService->formatPhoneNumber($phoneNumber, $country);
        
        return $this->json([
            'success' => true,
            'data' => [
                'isValid' => $isValid,
                'formatted' => $formatted
            ]
        ]);
    }
} 