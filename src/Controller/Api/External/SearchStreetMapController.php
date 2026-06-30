<?php

namespace App\Controller\Api\External;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/searchstreetmap', name: 'api_searchstreetmap_')]
class SearchStreetMapController extends AbstractController
{
    public function __construct(private LoggerInterface $logger) {}

    #[Route('/city', name: 'city', methods: ['GET'])]
    public function city(Request $request, ParameterBagInterface $params): JsonResponse
    {
        $urlApi = $params->get('url_api_data_gouv');
        $client = new Client();

        try {
            $response = $client->request('GET', $urlApi, [
                'query' => [
                    'q' => $request->query->get('city', ''),
                    'type' => 'municipality',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return new JsonResponse($data);
        } catch (RequestException $e) {
            $this->logger->error('Erreur lors de l’appel à l’API Adresse : ' . $e->getMessage());

            return new JsonResponse([
                'error' => 'Erreur lors de la recherche de ville.',
            ], 500);
        }
    }
}
