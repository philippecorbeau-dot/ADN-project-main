<?php

namespace App\Controller\Front\User;

use AllowDynamicProperties;

use App\Repository\User\UserRepository;
use App\Services\Mail\MailManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use App\Entity\User\Pro\UboDeclaration;
use App\Entity\User\Info;
use App\Entity\User\Pro;
use App\Entity\User\User;
use Twig\Error\LoaderError;
use App\Services\User\Info as InfoService;
use Psr\Log\LoggerInterface;
use App\Services\User\Pro\UboService;
use App\Services\KycNavigationService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use App\Entity\User\KycDocument;
use App\Form\Front\User\Create\KycStepDocumentsType;
use App\Form\Front\User\Create\Step5ProType;
use Symfony\Component\Security\Core\Security;
use App\Controller\Front\User\Steps\Step1;
use App\Controller\Front\User\Steps\Step2;
use App\Controller\Front\User\Steps\Step3;
use App\Controller\Front\User\Steps\Step4;
use App\Services\User\InvestorProfileScorer;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[Route('/register/kyc', name: 'user_')]
#[IsGranted('ROLE_USER')]
#[AllowDynamicProperties]
class AccountController extends AbstractController
{
    use Step1, Step2, Step3, Step4;
    
    const AVATAR_PATH = '/upload/users/avatar/';
    const AVATAR_EXTENSION = '.jpg';
    protected $uboService;

    private $logger;

    private InfoService $infoService;
    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface $eventDispatcher;
    private KycNavigationService $kycNavigationService;
    private InvestorProfileScorer $profileScorer;


    public function __construct(
        InfoService $infoService,
        LoggerInterface $logger,
        UboService $uboService,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher,
        KycNavigationService $kycNavigationService,
        InvestorProfileScorer $profileScorer
    )
    {
        $this->infoService = $infoService;
        $this->logger = $logger;
        $this->uboService = $uboService;
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->kycNavigationService = $kycNavigationService;
        $this->profileScorer = $profileScorer;
    }


    #[Route('/step/{step<\d+>?0}/{substep?}', name: 'create_profile')]
    public function createProfile(Request $request, int $step = 0, ?string $substep = null): Response
    {
        if ($this->logger) {
            try { $this->logger->info('[KYC createProfile][entry]', ['method' => $request->getMethod(), 'path' => $request->getPathInfo(), 'step' => $step, 'substep' => $substep]); } catch (\Throwable $e) {}
        }
        // Récupère les infos de session pour gérer une potentielle redirection.
        $session = $request->getSession();
        $redirUrl = $session->get('redirBackToSubscriptionUrl');
        $redirOptions = $session->get('redirBackToSubscriptionOptions');
        $user = $this->getUser();

        // Vérifier si l'utilisateur doit refaire le parcours KYC
        if ($this->kycNavigationService->shouldRestartKyc($user)) {
            // Si l'utilisateur doit refaire le parcours, on le redirige vers l'étape 1
            if ($step === 0) {
                return $this->redirectToRoute('user_create_profile', ['step' => 1]);
            }
            
            // Permettre l'accès à toutes les étapes pour la reprise
            $allowedStep = $step;
        } else {
            // Permettre la navigation libre entre les étapes pour les utilisateurs identifiés
            // ou pour les utilisateurs qui ont déjà complété le parcours
            $currentStep = $user->getStepKyc() ?? User::STEP_KYC_PROFILE;
            $isIdentified = $user->hasRole('ROLE_USER_IDENTIFIED') || $user->hasRole('ROLE_SUPER_ADMIN');
            
            if ($isIdentified || $currentStep >= 5) {
                // Pour les utilisateurs identifiés ou qui ont complété le parcours, permettre l'accès à toutes les étapes
                $allowedStep = $step;
            } else {
                // Enforce KYC step gating to prevent skipping steps (logique normale pour les nouveaux utilisateurs)
                if ($step > 1) {
                    $allowedStep = $currentStep + 1;
                    if ($step > $allowedStep) {
                        return $this->redirectToRoute('user_create_profile', ['step' => $allowedStep]);
                    }
                }
            }
        }
        
        $steplist = [
            'Profil',
            'Objectifs',
            'Patrimoine',
            $user->isPro() ? 'Situation financière' : 'Expérience',
            'Documents',
        ];
        // Obtenir les étapes accessibles pour la navigation
        $accessibleSteps = $this->kycNavigationService->getAccessibleSteps($user);
        
        $params = [
            'user' => $user,
            'step' => $step,
            'steplist' => $steplist,
            'accessibleSteps' => $accessibleSteps,
            'redirUrl' => $redirUrl,
            'redirOptions' => $redirOptions,
            // Variables supprimées car notification retirée du parcours KYC
            // 'canRestartKyc' => $this->kycNavigationService->canRestartKyc($user),
            // 'restartMessage' => $this->kycNavigationService->getRestartMessage($user),
        ];

        if ($step > 0) {
            if ($step == 5) {
                $stepParams = $this->kycStep5($request, $this->entityManager);
            } else {
                $func = 'step' . strtolower($step);
                if ($substep) {
                    // Transformer les tirets en camelCase pour les noms de méthodes
                    $camelCaseSubstep = str_replace('-', '', ucwords($substep, '-'));
                    $func .= $camelCaseSubstep;
                    $step .= $substep;
                    $params['substep'] = $substep;
                }
                $stepParams = $this->$func($request);
            }
            if ($stepParams instanceof RedirectResponse) {
                return $stepParams;
            }
            if (is_array($stepParams)) {
                $params += $stepParams;
            }
        }
        try {
            // Unifier uniquement l'étape 4 pour les PRO (l'étape 5 reste spécifique Pro)
            $stepStr = (string) $step;
            $isStep4 = preg_match('/^4/', $stepStr) === 1; // '4' ou '4...' (beginner, etc.)
            // Déterminer la section à afficher: pour les PRO on rend les vues "legal" (sauf step4 unifiée)
            $isProContext = ($user->isPro() || $user->getPro());
            $section = ($isProContext && !$isStep4) ? 'legal' : 'natural';
            return $this->render(sprintf('front/user/account/createprofile/%s/step%s.html.twig', $section, $step), $params);
        } catch (LoaderError $exception) {
            throw new NotFoundHttpException($exception->getMessage());
        }
    }

    public function kycStep5(Request $request, EntityManagerInterface $em)
    {
        $user = $this->getUser();
        // Étape 5 : formulaire différent pour les PRO (KBIS + documents légaux)
        if ($user->isPro()) {
            $form = $this->createForm(Step5ProType::class, $user->getPro());
        } else {
            $form = $this->createForm(KycStepDocumentsType::class);
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadDir = $this->getParameter('kernel.project_dir') . '/var/uploads/kyc/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

            if ($user->isPro()) {
                // PRO: KBIS + Statuts + Déclaration des bénéficiaires effectifs
                $identityFile = $form->has('identityProof') ? $form->get('identityProof')->getData() : null;
                $identityExpiration = $form->has('identityExpirationDate') ? $form->get('identityExpirationDate')->getData() : null;
                $registrationProof = $form->get('registrationProof')->getData();
                $articles = $form->get('articlesOfAssociation')->getData();
                $shareholderDecl = $form->get('shareholderDeclaration')->getData();

                if ($identityFile) {
                    if (!$identityExpiration) {
                        $this->addFlash('error', "Veuillez renseigner la date d'expiration de la pièce d'identité.");
                        return $this->redirectToRoute('user_create_profile', ['step' => 5]);
                    }
                    $filename = uniqid().'_'.$identityFile->getClientOriginalName();
                    $identityFile->move($uploadDir, $filename);
                    $doc = new KycDocument();
                    $doc->setUser($user);
                    $doc->setType('identity');
                    $doc->setFilename($filename);
                    $doc->setUploadedAt(new \DateTime());
                    $doc->setCreatedAt(new \DateTime());
                    $doc->setUpdatedAt(new \DateTime());
                    $doc->setStatus(KycDocument::STATUS_PENDING);
                    $doc->setExpirationDate($identityExpiration);
                    $em->persist($doc);
                }

                if ($registrationProof) {
                    $filename = uniqid().'_'.$registrationProof->getClientOriginalName();
                    $registrationProof->move($uploadDir, $filename);
                    $doc = new KycDocument();
                    $doc->setUser($user);
                    $doc->setType('registration_proof');
                    $doc->setFilename($filename);
                    $doc->setUploadedAt(new \DateTime());
                    $doc->setCreatedAt(new \DateTime());
                    $doc->setUpdatedAt(new \DateTime());
                    $doc->setStatus(KycDocument::STATUS_PENDING);
                    $em->persist($doc);
                }
                if ($articles) {
                    $filename = uniqid().'_'.$articles->getClientOriginalName();
                    $articles->move($uploadDir, $filename);
                    $doc = new KycDocument();
                    $doc->setUser($user);
                    $doc->setType('articles_of_association');
                    $doc->setFilename($filename);
                    $doc->setUploadedAt(new \DateTime());
                    $doc->setCreatedAt(new \DateTime());
                    $doc->setUpdatedAt(new \DateTime());
                    $doc->setStatus(KycDocument::STATUS_PENDING);
                    $em->persist($doc);
                }
                if ($shareholderDecl) {
                    $filename = uniqid().'_'.$shareholderDecl->getClientOriginalName();
                    $shareholderDecl->move($uploadDir, $filename);
                    $doc = new KycDocument();
                    $doc->setUser($user);
                    $doc->setType('shareholder_declaration');
                    $doc->setFilename($filename);
                    $doc->setUploadedAt(new \DateTime());
                    $doc->setCreatedAt(new \DateTime());
                    $doc->setUpdatedAt(new \DateTime());
                    $doc->setStatus(KycDocument::STATUS_PENDING);
                    $em->persist($doc);
                }
            } else {
                // Naturel: Identité + date d'expiration + justificatif de domicile
                $identityFile = $form->get('identityProof')->getData();
                $identityExpiration = $form->has('identityExpirationDate') ? $form->get('identityExpirationDate')->getData() : null;
                $addressFile = $form->get('addressProof')->getData();

                if ($identityFile) {
                    if (!$identityExpiration) {
                        $this->addFlash('error', "Veuillez renseigner la date d'expiration de votre pièce d'identité.");
                        return $this->redirectToRoute('user_create_profile', ['step' => 5]);
                    }
                    $filename = uniqid().'_'.$identityFile->getClientOriginalName();
                    $identityFile->move($uploadDir, $filename);
                    $doc = new KycDocument();
                    $doc->setUser($user);
                    $doc->setType('identity');
                    $doc->setFilename($filename);
                    $doc->setUploadedAt(new \DateTime());
                    $doc->setCreatedAt(new \DateTime());
                    $doc->setUpdatedAt(new \DateTime());
                    $doc->setStatus(KycDocument::STATUS_PENDING);
                    $doc->setExpirationDate($identityExpiration);
                    $em->persist($doc);
                }
                if ($addressFile) {
                    $filename = uniqid().'_'.$addressFile->getClientOriginalName();
                    $addressFile->move($uploadDir, $filename);
                    $doc = new KycDocument();
                    $doc->setUser($user);
                    $doc->setType('address');
                    $doc->setFilename($filename);
                    $doc->setUploadedAt(new \DateTime());
                    $doc->setCreatedAt(new \DateTime());
                    $doc->setUpdatedAt(new \DateTime());
                    $doc->setStatus(KycDocument::STATUS_PENDING);
                    $em->persist($doc);
                }
            }
            $em->flush();
            $this->addFlash('success', 'Documents uploadés avec succès !');
            // Redirection vers la page de récapitulatif & signature
            return $this->redirectToRoute('user_kyc_review');
        }
        $documents = $em->getRepository(KycDocument::class)->findBy(['user' => $user]);
        // Alimente les variables utilisées par les templates step5 pour éviter les erreurs Twig
        $params = [
            'form' => $form->createView(),
            'documents' => $documents,
            'validation' => false,
            'validated' => false,
            // Compatibilité éventuelle avec anciennes notations
            'arrayErrorMessages' => [],
            'arrayDisplayInputs' => [],
            'firstTime' => true,
            'kycHelper' => new class {
                public function constant(string $name): string { return $name; }
            },
        ];
        return $params;
    }

    #[Route('/review', name: 'kyc_review')]
    public function kycReview(Request $request): Response
    {
        $user = $this->getUser();
        $info = $user->getInfo();
        $pro = $user->getPro();
        $ik = $user->getInvestorKnowledge();
        $financialProducts = $ik ? $ik->getFinancialProductsKnowledge() : null;
        $complexProducts = $ik ? $ik->getComplexProductsKnowledge() : null;
        $marketExperience = $ik ? $ik->getMarketExperience() : null;
        $educationLevel = $ik ? $ik->getEducationLevel() : null;
        $investmentExperience = $ik ? $ik->getInvestmentExperience() : null;
        $documents = $this->entityManager->getRepository(KycDocument::class)->findBy(['user' => $user], ['uploadedAt' => 'DESC']);
        $signatureDoc = null;
        foreach ($documents as $d) {
            if (strtolower((string)$d->getType()) === 'signature') { $signatureDoc = $d; break; }
        }
        $signatureDoc = null;
        foreach ($documents as $d) {
            if (strtolower((string)$d->getType()) === 'signature') { $signatureDoc = $d; break; }
        }

        // Traductions lisibles pour l'étape 2
        $objectiveLabels = [];
        $investmentTermLabels = [];
        $sourceOfFundsLabels = [];
        if ($info) {
            $objMap = $info->getObjectiveList();
            foreach ((array) ($info->getObjective() ?? []) as $code) {
                $objectiveLabels[] = $objMap[$code] ?? (string) $code;
            }
            $termMap = [0 => 'Moins de 2 ans', 1 => 'Entre 2 et 6 ans', 2 => 'Plus de 6 ans', 3 => 'Je ne sais pas encore'];
            foreach ((array) ($info->getInvestmentTerm() ?? []) as $code) {
                $investmentTermLabels[] = $termMap[$code] ?? (string) $code;
            }
            $srcChoices = $this->infoService::getSourceOfFundsChoices();
            foreach ((array) ($info->getSourceOfFunds() ?? []) as $code) {
                // Le stockage peut être l'étiquette directe ou un index; supporter les deux
                $sourceOfFundsLabels[] = $srcChoices[$code] ?? (in_array($code, $srcChoices, true) ? $code : (string)$code);
            }
        }

        // Scoring/profil pour affichage (Step 4)
        $this->profileScorer->calculateAndUpdateProfile($user);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Libellés FR pour affichage des questionnaires
        $financialLabels = [
            "Quand je détiens une action d'une société, je lui prête de l'argent et suis rémunéré par les intérêts de ce prêt ?",
            "Quand une action donne un dividende, le cours de cette action diminue de la valeur de ce dividende ?",
            "Une obligation est toujours intégralement remboursée par son émetteur ?",
            "À maturité égale, une obligation notée AAA offre normalement un meilleur rendement qu'une obligation notée B ?",
            "Les OPCVM investis en actions sont généralement plus risqués que les OPCVM investis en obligations ?",
            "Un ETF (tracker) a pour objectif de reproduire la performance d'un indice ?",
            "La volatilité est un indicateur du risque d'un placement ?",
            "La performance absolue d'un placement ne peut pas être négative ?",
        ];
        $complexLabels = [
            "Les fonds alternatifs sont des OPC complexes ?",
            "Les FCPI/FIP/FCPR investissent dans des entreprises non cotées et peuvent offrir un avantage fiscal ?",
            "On peut sortir d'un FCPI/FIP/FCPR à tout moment sans frais ni contraintes ?",
            "Un produit structuré peut comporter plusieurs risques (émetteur, marché, etc.) ?",
            "Un produit structuré garantit toujours au moins 90% du capital ?",
            "La durée de vie d'un produit structuré est fixée au départ ?",
            "Une SCPI peut procurer des revenus réguliers issus des loyers ?",
            "Il est possible de perdre de l'argent avec une SCPI ?",
            "Un OPCI est lié aux marchés financiers ?",
            "Les produits à effet de levier sont plus risqués ?",
        ];
        $answerMap = ['true' => 'Vrai', 'false' => 'Faux', 'unknown' => 'Je ne sais pas'];
        $investmentExperienceLabels = [
            'hasLostSignificantAmounts' => 'Avez-vous déjà perdu des sommes significatives en bourse ? ',
            'managesOwnPortfolio' => 'Gérez-vous vous-même votre portefeuille ? ',
            'portfolioSecuritiesLines' => 'Sur combien de lignes de titres est réparti votre portefeuille ? ',
            'concentratesOnSingleSecurity' => 'Vous arrive-t-il de concentrer tout le portefeuille sur un seul titre ? ',
            'appropriatenessTestPerformed' => "Le test de caractère approprié est-il réalisé à chaque ordre d'achat sur produit complexe ? ",
            'ordersThroughCif' => 'Les ordres sur titre vif et/ou produits structurés ne doivent pas transiter par mon CIF ? ',
        ];
        $marketAbuseLabels = [
            'hasOtherSecuritiesAccounts' => "Avez-vous d'autres comptes-titres ?",
            'hasFinancialProfession' => 'Exercez-vous ou avez-vous exercé une profession dans le domaine financier ou boursier ? ',
            'isListedCompanyDirector' => "Êtes-vous dirigeant d'une société cotée ou de la maison mère d'une société cotée ?",
            'professionDetails' => 'Détails de la profession',
            'listedCompanyDetails' => 'Détails société cotée',
        ];

        // Autoriser la re-signature si le KYC doit être refait ou si query resign=1
        $canResign = false;
        try { $canResign = $this->kycNavigationService->shouldRestartKyc($user); } catch (\Throwable $e) {}
        $canResign = $canResign || $request->query->getBoolean('resign', false);

        return $this->render('front/user/account/createprofile/kyc_review.html.twig', [
            'user' => $user,
            'info' => $info,
            'pro' => $pro,
            'documents' => $documents,
            'objectiveLabels' => $objectiveLabels,
            'investmentTermLabels' => $investmentTermLabels,
            'sourceOfFundsLabels' => $sourceOfFundsLabels,
            'investorProfile' => $user->getInvestorProfile(),
            'investorScore' => $user->getInvestorScore(),
            'signatureDoc' => $signatureDoc,
            // Questionnaire de connaissances
            'ik' => $ik,
            'financialProducts' => $financialProducts,
            'complexProducts' => $complexProducts,
            'marketExperience' => $marketExperience,
            'educationLevel' => $educationLevel,
            'investmentExperience' => $investmentExperience,
            'financialLabels' => $financialLabels,
            'complexLabels' => $complexLabels,
            'answerMap' => $answerMap,
            'investmentExperienceLabels' => $investmentExperienceLabels,
            'marketAbuseLabels' => $marketAbuseLabels,
            'canResign' => $canResign,
        ]);
    }

    #[Route('/review/sign', name: 'kyc_sign', methods: ['POST'])]
    public function kycSign(Request $request): Response
    {
        $user = $this->getUser();
        $signatureDataUrl = (string) ($request->request->get('signature') ?? '');
        if ($signatureDataUrl) {
            // Enregistrer la signature comme document KYC (image PNG base64)
            $uploadDir = $this->getParameter('kernel.project_dir') . '/var/uploads/kyc/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
            $image = preg_replace('#^data:image/\w+;base64,#i', '', $signatureDataUrl);
            $image = base64_decode($image);
            $filename = 'signature_' . $user->getId() . '_' . date('Ymd_His') . '.png';
            file_put_contents($uploadDir . $filename, $image);

            $doc = new KycDocument();
            $doc->setUser($user);
            $doc->setType('signature');
            $doc->setFilename($filename);
            $doc->setUploadedAt(new \DateTime());
            $doc->setCreatedAt(new \DateTime());
            $doc->setUpdatedAt(new \DateTime());
            $doc->setStatus(KycDocument::STATUS_PENDING);
            $doc->setIpAddress($request->getClientIp());
            $doc->setSignedAt(new \DateTime());
            $this->entityManager->persist($doc);
        }

        // Mettre à jour l'étape: le prochain écran est le profil investisseur
        if (!$user->hasRole('ROLE_USER_IDENTIFIED')) {
            $user->setStepKyc(User::STEP_KYC_PROFILE);
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/review/pdf', name: 'kyc_pdf', methods: ['GET'])]
    public function kycPdf(Request $request): Response
    {
        $user = $this->getUser();
        $info = $user->getInfo();
        $pro = $user->getPro();
        $documents = $this->entityManager->getRepository(KycDocument::class)->findBy(['user' => $user], ['uploadedAt' => 'DESC']);
        $ik = $user->getInvestorKnowledge();
        $financialProducts = $ik ? $ik->getFinancialProductsKnowledge() : null;
        $complexProducts = $ik ? $ik->getComplexProductsKnowledge() : null;
        $marketExperience = $ik ? $ik->getMarketExperience() : null;
        $educationLevel = $ik ? $ik->getEducationLevel() : null;
        $investmentExperience = $ik ? $ik->getInvestmentExperience() : null;
        $open = $request->query->getBoolean('open', false);

        // Récupérer le logo ADN Family Office pour l'entête du PDF (priorité: build)
        $logoDataUrl = null;
        $projectDir = $this->getParameter('kernel.project_dir');
        $preferred = [
            $projectDir . '/public/build/theme/images/ADN_Logo_enlarged_x5.png',
            $projectDir . '/public/build/theme/images/ADN_Logo.png',
            $projectDir . '/public/theme/images/ADN_Logo_enlarged_x5.png',
            $projectDir . '/public/theme/images/ADN_Logo.png',
        ];
        foreach ($preferred as $path) {
            if (is_file($path)) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mime = $ext === 'svg' ? 'image/svg+xml' : ($ext === 'png' ? 'image/png' : 'image/jpeg');
                $content = @file_get_contents($path);
                if ($content !== false) {
                    $logoDataUrl = 'data:' . $mime . ';base64,' . base64_encode($content);
                    break;
                }
            }
        }

        // Traductions (mêmes que la page)
        $objectiveLabels = [];
        $investmentTermLabels = [];
        $sourceOfFundsLabels = [];
        if ($info) {
            $objMap = $info->getObjectiveList();
            foreach ((array) ($info->getObjective() ?? []) as $code) {
                $objectiveLabels[] = $objMap[$code] ?? (string) $code;
            }
            $termMap = [0 => 'Moins de 2 ans', 1 => 'Entre 2 et 6 ans', 2 => 'Plus de 6 ans', 3 => 'Je ne sais pas encore'];
            foreach ((array) ($info->getInvestmentTerm() ?? []) as $code) {
                $investmentTermLabels[] = $termMap[$code] ?? (string) $code;
            }
            $srcChoices = $this->infoService::getSourceOfFundsChoices();
            foreach ((array) ($info->getSourceOfFunds() ?? []) as $code) {
                $sourceOfFundsLabels[] = $srcChoices[$code] ?? (in_array($code, $srcChoices, true) ? $code : (string)$code);
            }
        }

        try {
            // Rendre un HTML dédié au PDF
            // Libellés + mapping FR
            $financialLabels = [
                "Quand je détiens une action d'une société, je lui prête de l'argent et suis rémunéré par les intérêts de ce prêt ?",
                "Quand une action donne un dividende, le cours de cette action diminue de la valeur de ce dividende ?",
                "Une obligation est toujours intégralement remboursée par son émetteur ?",
                "À maturité égale, une obligation notée AAA offre normalement un meilleur rendement qu'une obligation notée B ?",
                "Les OPCVM investis en actions sont généralement plus risqués que les OPCVM investis en obligations ?",
                "Un ETF (tracker) a pour objectif de reproduire la performance d'un indice ?",
                "La volatilité est un indicateur du risque d'un placement ?",
                "La performance absolue d'un placement ne peut pas être négative ?",
            ];
            $complexLabels = [
                "Les fonds alternatifs sont des OPC complexes ?",
                "Les FCPI/FIP/FCPR investissent dans des entreprises non cotées et peuvent offrir un avantage fiscal ?",
                "On peut sortir d'un FCPI/FIP/FCPR à tout moment sans frais ni contraintes ?",
                "Un produit structuré peut comporter plusieurs risques (émetteur, marché, etc.) ?",
                "Un produit structuré garantit toujours au moins 90% du capital ?",
                "La durée de vie d'un produit structuré est fixée au départ ?",
                "Une SCPI peut procurer des revenus réguliers issus des loyers ?",
                "Il est possible de perdre de l'argent avec une SCPI ?",
                "Un OPCI est lié aux marchés financiers ?",
                "Les produits à effet de levier sont plus risqués ?",
            ];
            $answerMap = ['true' => 'Vrai', 'false' => 'Faux', 'unknown' => 'Je ne sais pas'];
            $investmentExperienceLabels = [
                'hasLostSignificantAmounts' => 'Avez-vous déjà perdu des sommes significatives en bourse ? ',
                'managesOwnPortfolio' => 'Gérez-vous vous-même votre portefeuille ? ',
                'portfolioSecuritiesLines' => 'Sur combien de lignes de titres est réparti votre portefeuille ? ',
                'concentratesOnSingleSecurity' => 'Vous arrive-t-il de concentrer tout le portefeuille sur un seul titre ? ',
                'appropriatenessTestPerformed' => "Le test de caractère approprié est-il réalisé à chaque ordre d'achat sur produit complexe ? ",
                'ordersThroughCif' => 'Les ordres sur titre vif et/ou produits structurés ne doivent pas transiter par mon CIF ? ',
            ];
            $marketAbuseLabels = [
                'hasOtherSecuritiesAccounts' => "Avez-vous d'autres comptes-titres ?",
                'hasFinancialProfession' => 'Exercez-vous ou avez-vous exercé une profession dans le domaine financier ou boursier ? ',
                'isListedCompanyDirector' => "Êtes-vous dirigeant d'une société cotée ou de la maison mère d'une société cotée ?",
                'professionDetails' => 'Détails de la profession',
                'listedCompanyDetails' => 'Détails société cotée',
            ];

            $html = $this->renderView('front/user/account/createprofile/kyc_review_pdf.html.twig', [
                'user' => $user,
                'info' => $info,
                'pro' => $pro,
                'documents' => $documents,
                'objectiveLabels' => $objectiveLabels,
                'investmentTermLabels' => $investmentTermLabels,
                'sourceOfFundsLabels' => $sourceOfFundsLabels,
                'investorProfile' => $user->getInvestorProfile(),
                'investorScore' => $user->getInvestorScore(),
                'logoDataUrl' => $logoDataUrl,
                'ik' => $ik,
                'financialProducts' => $financialProducts,
                'complexProducts' => $complexProducts,
                'marketExperience' => $marketExperience,
                'educationLevel' => $educationLevel,
                'investmentExperience' => $investmentExperience,
                'investmentExperienceLabels' => $investmentExperienceLabels,
                'financialLabels' => $financialLabels,
                'complexLabels' => $complexLabels,
                'answerMap' => $answerMap,
                'marketAbuseLabels' => $marketAbuseLabels,
                'generatedAt' => new \DateTime(),
            ]);

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->setIsRemoteEnabled(true);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $output = $dompdf->output();
            $pdfDir = $this->getParameter('kernel.project_dir') . '/var/uploads/kyc/';
            if (!is_dir($pdfDir)) { mkdir($pdfDir, 0777, true); }
            $pdfFilename = 'kyc_recap_' . $user->getId() . '_' . date('Ymd_His') . '.pdf';
            file_put_contents($pdfDir . $pdfFilename, $output);

            // Selon le contexte, retourner JSON (appel via fetch) ou ouvrir directement
            // Note: $open déjà défini plus haut
            $fileUrl = $this->generateUrl('user_kyc_file', ['filename' => $pdfFilename]);
            if ($open) {
                return $this->redirect($fileUrl);
            }
            return new JsonResponse(['url' => $fileUrl]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => true, 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/file/{filename}', name: 'kyc_file', requirements: ['filename' => '.+'])]
    public function kycFile(string $filename): Response
    {
        $safe = basename($filename);
        $path = $this->getParameter('kernel.project_dir') . '/var/uploads/kyc/' . $safe;
        if (!is_file($path)) {
            throw new NotFoundHttpException('Fichier introuvable');
        }
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $safe);
        return $response;
    }

    #[Route('/success', name: 'kyc_success')]
    public function kycSuccess(): Response
    {
        return $this->render('front/user/account/createprofile/kyc_success.html.twig');
    }

    #[Route('/profile/result', name: 'investor_profile')]
    public function investorProfile(): Response
    {
        $user = $this->getUser();
        // Recharger l'utilisateur depuis la base pour éviter tout état périmé en session
        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(\App\Entity\User\User::class)->find($user->getId());
        // Forcer l'initialisation des proxys nécessaires
        if ($user->getInfo()) { $user->getInfo()->getId(); }
        if ($user->getInvestorKnowledge()) { $user->getInvestorKnowledge()->getId(); }
        // Rafraîchir Info depuis la DB pour éviter les champs obsolètes
        if ($user->getInfo()) {
            // Recharger Info par requête native pour garantir les champs à jour
            $conn = $this->entityManager->getConnection();
            $sql = 'SELECT objective, investmentTerm, liquidity, realestate, account_securities, capitalisation, scpi, income, mif, awareness_minimum_amount, awareness_minimum_time, awareness_minimum_transactions FROM user_info WHERE id = :id';
            $row = $conn->fetchAssociative($sql, ['id' => $user->getInfo()->getId()]);
            if ($row) {
                $info = $user->getInfo();
                $info->setObjective($row['objective'] ? @unserialize($row['objective']) : null);
                $info->setInvestmentTerm($row['investmentTerm'] ? @unserialize($row['investmentTerm']) : null);
                $info->setLiquidity($row['liquidity'] !== null ? (int)$row['liquidity'] : null);
                $info->setRealestate($row['realestate'] !== null ? (int)$row['realestate'] : null);
                $info->setAccountSecurities($row['account_securities'] !== null ? (int)$row['account_securities'] : null);
                $info->setCapitalisation($row['capitalisation'] !== null ? (int)$row['capitalisation'] : null);
                $info->setScpi($row['scpi'] !== null ? (int)$row['scpi'] : null);
                $info->setIncome($row['income'] !== null ? (int)$row['income'] : null);
                $info->setMif((bool)$row['mif']);
                $info->setAwarenessMinimumAmount((bool)$row['awareness_minimum_amount']);
                $info->setAwarenessMinimumTime((bool)$row['awareness_minimum_time']);
                $info->setAwarenessTransactions((bool)$row['awareness_minimum_transactions']);
            }
        }
        // Calcul/rafraîchissement du profil
        $this->profileScorer->calculateAndUpdateProfile($user);
        // Persister le profil calculé afin que le back-office voie immédiatement le changement
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $score = $user->getInvestorScore();
        $profile = $user->getInvestorProfile();
        $color = $this->profileScorer->getProfileColor($profile);
        $description = $this->profileScorer->getProfileDescription($profile);
        $recommendations = $this->profileScorer->getProductRecommendations($profile);

        $breakdown = $this->profileScorer->getLastBreakdown();
        $knowledgeMax = 84;
        $knowledgeScore = $breakdown['knowledge_raw'] ?? 0;
        $knowledgeRatio = $knowledgeMax > 0 ? round(($knowledgeScore / $knowledgeMax) * 100) : 0;

        $info = $user->getInfo();
        $step2Details = [
            'objectives' => $info?->getObjective() ?? [],
            'investmentTerm' => $info?->getInvestmentTerm() ?? [],
            'note' => $breakdown['step2_note'] ?? null,
        ];

        // Poids et bornes max (mêmes valeurs que dans le service par défaut)
        $weights = [
            'step2' => 20,
            'step3' => 35,
            'step4' => 15,
            'knowledge' => 30,
        ];
        $maxRaw = [
            'step2' => 40,
            'step3' => 60,
            'step4' => 60,
            'knowledge' => 84,
        ];

        $steps = [
            'step2' => [
                'label' => 'Objectifs et horizon (Step 2)',
                'raw' => $breakdown['step2_raw'] ?? 0,
                'max' => $maxRaw['step2'],
                'ratio' => isset($breakdown['step2_ratio']) ? round(($breakdown['step2_ratio'] ?? 0) * 100) : 0,
                'weighted' => round($breakdown['step2_weighted'] ?? 0, 2),
                'weight' => $weights['step2'],
            ],
            'step3' => [
                'label' => 'Patrimoine & revenus (Step 3)',
                'raw' => $breakdown['step3_raw'] ?? 0,
                'max' => $maxRaw['step3'],
                'ratio' => isset($breakdown['step3_ratio']) ? round(($breakdown['step3_ratio'] ?? 0) * 100) : 0,
                'weighted' => round($breakdown['step3_weighted'] ?? 0, 2),
                'weight' => $weights['step3'],
            ],
            'step4' => [
                'label' => 'Expérience & MIF (Step 4)',
                'raw' => $breakdown['step4_raw'] ?? 0,
                'max' => $maxRaw['step4'],
                'ratio' => isset($breakdown['step4_ratio']) ? round(($breakdown['step4_ratio'] ?? 0) * 100) : 0,
                'weighted' => round($breakdown['step4_weighted'] ?? 0, 2),
                'weight' => $weights['step4'],
            ],
            'knowledge' => [
                'label' => 'Questionnaire connaissances',
                'raw' => $knowledgeScore,
                'max' => $maxRaw['knowledge'],
                'ratio' => $knowledgeRatio,
                'weighted' => round($breakdown['knowledge_weighted'] ?? 0, 2),
                'weight' => $weights['knowledge'],
            ],
        ];

        $safetyAdjusted = $breakdown['safety_adjusted_total'] ?? $score;

        // Cumulés normalisés par étape (pondérés)
        $cumulative = [];
        $sum = 0.0;
        foreach (['step2','step3','step4','knowledge'] as $k) {
            $sum += (float)($steps[$k]['weighted'] ?? 0);
            $cumulative[$k] = round($sum, 2);
        }

        return $this->render('front/user/account/createprofile/investor_profile.html.twig', [
            'score' => $score,
            'profile' => $profile,
            'color' => $color,
            'description' => $description,
            'recommendations' => $recommendations,
            'breakdown' => $breakdown,
            'steps' => $steps,
            'knowledgeScore' => $knowledgeScore,
            'knowledgeMax' => $knowledgeMax,
            'knowledgeRatio' => $knowledgeRatio,
            'safetyAdjustedScore' => $safetyAdjusted,
            'calculatedAt' => $user->getInvestorProfileCalculatedAt(),
            'step2Details' => $step2Details,
            'cumulative' => $cumulative,
        ]);
    }
}
