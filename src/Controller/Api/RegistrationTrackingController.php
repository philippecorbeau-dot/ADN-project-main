<?php

namespace App\Controller\Api;

use App\Entity\RegistrationTracking;
use App\Repository\RegistrationTrackingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/registration-tracking')]
class RegistrationTrackingController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RegistrationTrackingRepository $trackingRepository,
    ) {}

    #[Route('/track', name: 'api_registration_tracking', methods: ['POST'])]
    public function track(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return new JsonResponse(['success' => false, 'error' => 'Invalid JSON'], 400);
            }

            // Récupérer ou créer un tracking existant basé sur l'email ou créer un nouveau
            $tracking = null;
            if (!empty($data['email'])) {
                $tracking = $this->trackingRepository->findOneBy(
                    ['email' => $data['email'], 'completed' => false],
                    ['createdAt' => 'DESC']
                );
            }

            if (!$tracking) {
                $tracking = new RegistrationTracking();
            }

            // Mettre à jour les données
            if (isset($data['firstName'])) {
                $tracking->setFirstName($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $tracking->setLastName($data['lastName']);
            }
            if (isset($data['email'])) {
                $tracking->setEmail($data['email']);
            }
            if (isset($data['profileType'])) {
                $tracking->setProfileType($data['profileType']);
            }

            // Enregistrer l'IP et le User-Agent
            $tracking->setIpAddress($request->getClientIp());
            $tracking->setUserAgent($request->headers->get('User-Agent'));

            // Marquer comme complété si toutes les données sont présentes
            if (!empty($data['firstName']) && !empty($data['lastName']) && !empty($data['email']) && !empty($data['profileType'])) {
                $tracking->setCompleted(true);
            }

            $this->em->persist($tracking);
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'id' => $tracking->getId(),
                'message' => 'Tracking enregistré avec succès'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/list', name: 'api_registration_tracking_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $limit = (int) ($request->query->get('limit', 50));
            $incompleteOnly = $request->query->getBoolean('incomplete_only', false);

            if ($incompleteOnly) {
                $trackings = $this->trackingRepository->findRecentIncomplete($limit);
            } else {
                $trackings = $this->trackingRepository->findRecent($limit);
            }

            $data = array_map(function (RegistrationTracking $tracking) {
                return [
                    'id' => $tracking->getId(),
                    'firstName' => $tracking->getFirstName(),
                    'lastName' => $tracking->getLastName(),
                    'email' => $tracking->getEmail(),
                    'profileType' => $tracking->getProfileType(),
                    'createdAt' => $tracking->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'updatedAt' => $tracking->getUpdatedAt()?->format('Y-m-d H:i:s'),
                    'ipAddress' => $tracking->getIpAddress(),
                    'completed' => $tracking->isCompleted(),
                ];
            }, $trackings);

            return new JsonResponse([
                'success' => true,
                'data' => $data,
                'count' => count($data)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur lors de la récupération: ' . $e->getMessage()
            ], 500);
        }
    }
}

