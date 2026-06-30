<?php

declare(strict_types=1);

namespace App\Controller\Front\User;


use App\Entity\User\KycDocument;
use App\Entity\User\User;
use App\Repository\User\KycDocumentRepository;
use App\Services\KycNotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route("/user", name: "user_")]
class InvestmentController extends AbstractController
{
    public function __construct(
        private readonly KycDocumentRepository $kycDocumentRepository,
        private readonly KycNotificationService $kycNotificationService,
    )
    {}

    #[Route("/dashboard-old", name: "dashboard_old")]
    #[IsGranted("ROLE_USER")]
    public function dashboardOld(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $projects = [
            (object)['name' => 'Projet Alpha', 'status' => 'En cours'],
            (object)['name' => 'Projet Beta', 'status' => 'Terminé'],
        ];

        $scpis = [];
        $vefas = [];

        $sums = [
            'capitalRefund' => 2000,
            'sumInterestsGross' => 500,
            'sumTaxes' => 100,
        ];

        $entityKycDoc = new KycDocument();
        $listRefusedReasonMessage = $entityKycDoc->getRefusedReasonMessageList();
        $listDocTypes = $entityKycDoc->getTypeList();

        $arrayDataLatestKycDocs = $this->getDataLatestDocsByType($user);
        $lastDocsInvalidated = false;
        $arrayErrors = [];
        $allValidatedDocs = !empty($arrayDataLatestKycDocs);
        
        // Utiliser le service de notification KYC
        $kycStatus = $this->kycNotificationService->getKycNotificationStatus($user);
        
        // Vérifier si l'utilisateur doit refaire le parcours KYC
        $restartKycNotification = $this->kycNotificationService->getRestartKycNotification($user);
        
        foreach ($arrayDataLatestKycDocs as $statusKycDoc => $dataKycDoc) {
            if ($statusKycDoc === KycDocument::STATUS_REFUSED || $statusKycDoc === KycDocument::STATUS_OUTDATED) {
                foreach ($dataKycDoc as $data) {
                    foreach ($data as $docType => $refusedReasonMessage) {
                        if (isset($refusedReasonMessage)) {
                            $arrayErrors[$listDocTypes[$docType]] = $listRefusedReasonMessage[$refusedReasonMessage];
                        }
                    }
                }

                $lastDocsInvalidated = true;
            }

            if ($statusKycDoc !== KycDocument::STATUS_VALIDATED) {
                $allValidatedDocs = false;
            }
        }

        $kycDocs = $user->getKycDocuments();
        $nbKycDocs = count($kycDocs);
        $isCompleted = $nbKycDocs > 0;
        $verifying = $isCompleted && !$lastDocsInvalidated && !$user->hasRole(User::ROLE_KYC_OUTDATED);

        $totalInvestedInprogressStatusProject = 3000;

        $arrayKycDocsMissing = [];
        if ($verifying === true && $nbKycDocs > 0) {
            $arrayKycDocsMissing = [KycDocument::DOCUMENT_TYPE_IDENTITY_PROOF];

            if ($user->isPrivate() || $user->isCgp()) {
                $arrayKycDocsMissing[] = KycDocument::DOCUMENT_TYPE_ADDRESS_PROOF;
            } elseif ($user->isPro()) {
                $arrayKycDocsMissing[] = KycDocument::DOCUMENT_TYPE_REGISTRATION_PROOF;
                $arrayKycDocsMissing[] = KycDocument::DOCUMENT_TYPE_ARTICLES_OF_ASSOCIATION;
                $arrayKycDocsMissing[] = KycDocument::DOCUMENT_TYPE_SHAREHOLDER_DECLARATION;
            }

            foreach ($kycDocs as $doc) {
                if (in_array($doc->getType(), $arrayKycDocsMissing)) {
                    $key = array_search($doc->getType(), $arrayKycDocsMissing);
                    if ($key !== false) {
                        unset($arrayKycDocsMissing[$key]);
                        $arrayKycDocsMissing = array_values($arrayKycDocsMissing);
                    }
                }
            }
        }

        return $this->render('front/user/investment/dashboard.html.twig', [
            'mangopayInfo' => null,
            'totalInvest' => 10000,
            'remainingPrincipalAmount' => 10000 - $sums['capitalRefund'],
            'repaidCapitalAndInterest' => $sums['capitalRefund'] + $sums['sumInterestsGross'] - $sums['sumTaxes'],
            'totalRefunded' => 2000,
            'refunds' => [],
            'refundDates' => [],
            'projects' => $projects,
            'scpis' => $scpis,
            'vefas' => $vefas,
            'isCompleted' => $isCompleted,
            'verifying' => $verifying,
            'totalInvestedInprogressStatusProject' => $totalInvestedInprogressStatusProject,
            'lastOpenedProjectIndex' => null,
            'user' => $user,
            'kycDocsMissing' => $arrayKycDocsMissing,
            'lastDocsInvalidated' => $lastDocsInvalidated,
            'allValidatedDocs' => $allValidatedDocs,
            'arrayErrors' => $arrayErrors,
            'kycStatus' => $kycStatus, // Utilise le service de notification
            'restartKycNotification' => $restartKycNotification, // Ajout de la notification de reprise
        ]);
    }

    private function getDataLatestDocsByType(User $user): array
    {
        $user = $this->getUser();
        $arrayDataLatestKycDocs = [];

        $latestIdentityProofDoc = $this->kycDocumentRepository->findDataLatestDocByUserAndType($user, KycDocument::DOCUMENT_TYPE_IDENTITY_PROOF);
        if (!is_null($latestIdentityProofDoc)) {
            $arrayDataLatestKycDocs[$latestIdentityProofDoc['status']][] = [
                $latestIdentityProofDoc['type'] => $latestIdentityProofDoc['refusedReasonMessage'],
            ];
        }

        if ($user->isPrivate() || $user->isCgp()) {
            $latestAddressProofDoc = $this->kycDocumentRepository->findDataLatestDocByUserAndType($user, KycDocument::DOCUMENT_TYPE_ADDRESS_PROOF);
            if (!is_null($latestAddressProofDoc)) {
                $arrayDataLatestKycDocs[$latestAddressProofDoc['status']][] = [
                    $latestAddressProofDoc['type'] => $latestAddressProofDoc['refusedReasonMessage'],
                ];
            }
        } elseif ($user->isPro()) {
            $latestRegistrationProofDoc = $this->kycDocumentRepository->findDataLatestDocByUserAndType($user, KycDocument::DOCUMENT_TYPE_REGISTRATION_PROOF);
            if (!is_null($latestRegistrationProofDoc)) {
                $arrayDataLatestKycDocs[$latestRegistrationProofDoc['status']][] = [
                    $latestRegistrationProofDoc['type'] => $latestRegistrationProofDoc['refusedReasonMessage'],
                ];
            }

            $latestArticlesAssociationProofDoc = $this->kycDocumentRepository->findDataLatestDocByUserAndType($user, KycDocument::DOCUMENT_TYPE_ARTICLES_OF_ASSOCIATION);
            if (!is_null($latestArticlesAssociationProofDoc)) {
                $arrayDataLatestKycDocs[$latestArticlesAssociationProofDoc['status']][] = [
                    $latestArticlesAssociationProofDoc['type'] => $latestArticlesAssociationProofDoc['refusedReasonMessage'],
                ];
            }

            $latestShareHolderDeclarationProofDoc = $this->kycDocumentRepository->findDataLatestDocByUserAndType($user, KycDocument::DOCUMENT_TYPE_SHAREHOLDER_DECLARATION);
            if (!is_null($latestArticlesAssociationProofDoc)) {
                $arrayDataLatestKycDocs[$latestShareHolderDeclarationProofDoc['status']][] = [
                    $latestShareHolderDeclarationProofDoc['type'] => $latestShareHolderDeclarationProofDoc['refusedReasonMessage'],
                ];
            }
        }

        return $arrayDataLatestKycDocs;
    }
}
