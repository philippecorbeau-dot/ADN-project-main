<?php

namespace App\Controller\Front\User\Steps;

use App\Entity\User\User;
use App\Form\Front\User\Create\Step3ProType;
use App\Form\Front\User\Create\Step3Type;
use App\Services\User\InvestorProfileScorer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

trait Step3
{
    protected function step3(Request $request): array|RedirectResponse
    {
        $user = $this->getUser();
        if (property_exists($this, 'logger') && $this->logger) {
            try { $this->logger->info('[KYC Step3][request]', ['method' => $request->getMethod(), 'uri' => $request->getRequestUri()]); } catch (\Throwable $e) {}
        }

        if ($user->isPro()) {
            $form = $this->createForm(Step3ProType::class, $user->getPro());
        } else {
            // S'assurer que l'entité Info existe et qu'elle est liée au User
            if (!$user->getInfo()) {
                $info = new \App\Entity\User\Info();
                $user->setInfo($info);
                if (method_exists($info, 'setUser')) {
                    $info->setUser($user);
                }
                $this->entityManager->persist($info);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            }
            // Hydrater l'entité Info (éviter proxys non initialisés)
            $info = $user->getInfo();
            try {
                if ($info instanceof \ProxyManager\Proxy\LazyLoadingInterface) { $info->initializeProxy(); }
                elseif ($info instanceof \Doctrine\Persistence\Proxy) { $info->__load(); }
            } catch (\Throwable $e) {}
            $form = $this->createForm(Step3Type::class, $info);
        }
        
        $em = $this->entityManager;
        $form->handleRequest($request);
        if (property_exists($this, 'logger') && $this->logger) {
            try { $this->logger->info('[KYC Step3][payload_raw]', ['post' => $request->request->all()]); } catch (\Throwable $e) {}
        }
        
        if ($form->isSubmitted()) {
            // Si c'est juste une sauvegarde temporaire
            if ($request->request->get('_save_only')) {
                // Hydrater depuis le payload puis sauvegarder immédiatement
                $formName = $form->getName();
                $payloadAll = $request->request->all();
                $payload = isset($payloadAll[$formName]) ? $payloadAll[$formName] : [];
                $safeInt = static function($val): int { $s = is_string($val) ? preg_replace('/[^\d]/', '', $val) : $val; return (int) ($s ?: 0); };
                if (!empty($payload) && !$user->isPro()) {
                    $info = $user->getInfo();
                    $info->setSalary($safeInt($payload['salary'] ?? null));
                    $info->setAccountSecurities($safeInt($payload['accountSecurities'] ?? null));
                    // Champs détaillés dépôt/épargne (nouveaux)
                    $info->setDepositSavingsChecking($safeInt($payload['depositSavingsChecking'] ?? null));
                    $info->setDepositSavingsLivretA($safeInt($payload['depositSavingsLivretA'] ?? null));
                    $info->setDepositSavingsLdd($safeInt($payload['depositSavingsLdd'] ?? null));
                    $info->setDepositSavingsCsl($safeInt($payload['depositSavingsCsl'] ?? null));
                    $info->setDepositSavingsOther($safeInt($payload['depositSavingsOther'] ?? null));
                    // Somme automatique vers le total (depositSavings)
                    $sum = 0;
                    foreach (['depositSavingsChecking','depositSavingsLivretA','depositSavingsLdd','depositSavingsCsl','depositSavingsOther'] as $k) {
                        $sum += $safeInt($payload[$k] ?? 0);
                    }
                    $info->setDepositSavings($sum > 0 ? $sum : null);
                    $info->setCapitalisation($safeInt($payload['capitalisation'] ?? null));
                    $info->setScpi($safeInt($payload['scpi'] ?? null));
                    $info->setRealestateIncome($safeInt($payload['realestateIncome'] ?? null));
                    // Décomposition immobilière + total
                    $info->setRealestatePrimaryResidence($safeInt($payload['realestatePrimaryResidence'] ?? null));
                    $info->setRealestateInvestment($safeInt($payload['realestateInvestment'] ?? null));
                    $realestateTotal = $safeInt($payload['realestatePrimaryResidence'] ?? 0)
                        + $safeInt($payload['realestateInvestment'] ?? 0)
                        + $safeInt($payload['scpi'] ?? 0);
                    $info->setRealestate($realestateTotal > 0 ? $realestateTotal : null);
                    $info->setRent($safeInt($payload['rent'] ?? null));
                    $em->persist($info);
                } elseif ($user->isPro() && $user->getPro()) {
                    $em->persist($user->getPro());
                }
                $em->persist($user);
                $em->flush();

                if (property_exists($this, 'profileScorer') && $this->profileScorer instanceof InvestorProfileScorer) {
                    $this->profileScorer->calculateAndUpdateProfile($user);
                    $em->persist($user);
                    $em->flush();
                }
                // Redirection directe vers l'étape suivante (expérience)
                return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_EXPERIENCE]);
            }
            
            // Hydratation défensive à partir du POST, même si des widgets Twig auraient changé
            $formName = $form->getName();
            // Certaines versions renvoient un InputBag; utilisons all() pour garantir un tableau
            $payloadBag = $request->request->all();
            $payload = isset($payloadBag[$formName]) ? $payloadBag[$formName] : [];
            $safeInt = static function($val): int { $s = is_string($val) ? preg_replace('/[^\d]/', '', $val) : $val; return (int) ($s ?: 0); };

            if (!empty($payload)) {
                $info = $user->getInfo();
                $info->setSalary($safeInt($payload['salary'] ?? null));
                $info->setAccountSecurities($safeInt($payload['accountSecurities'] ?? null));
                // Champs détaillés dépôt/épargne + somme
                $info->setDepositSavingsChecking($safeInt($payload['depositSavingsChecking'] ?? null));
                $info->setDepositSavingsLivretA($safeInt($payload['depositSavingsLivretA'] ?? null));
                $info->setDepositSavingsLdd($safeInt($payload['depositSavingsLdd'] ?? null));
                $info->setDepositSavingsCsl($safeInt($payload['depositSavingsCsl'] ?? null));
                $info->setDepositSavingsOther($safeInt($payload['depositSavingsOther'] ?? null));
                $sum = 0;
                foreach (['depositSavingsChecking','depositSavingsLivretA','depositSavingsLdd','depositSavingsCsl','depositSavingsOther'] as $k) { $sum += $safeInt($payload[$k] ?? 0); }
                $info->setDepositSavings($sum > 0 ? $sum : null);
                $info->setCapitalisation($safeInt($payload['capitalisation'] ?? null));
                $info->setScpi($safeInt($payload['scpi'] ?? null));
                $info->setRealestateIncome($safeInt($payload['realestateIncome'] ?? null));
                $info->setRealestatePrimaryResidence($safeInt($payload['realestatePrimaryResidence'] ?? null));
                $info->setRealestateInvestment($safeInt($payload['realestateInvestment'] ?? null));
                $realestateTotal = $safeInt($payload['realestatePrimaryResidence'] ?? 0)
                    + $safeInt($payload['realestateInvestment'] ?? 0)
                    + $safeInt($payload['scpi'] ?? 0);
                $info->setRealestate($realestateTotal > 0 ? $realestateTotal : null);
                $info->setRent($safeInt($payload['rent'] ?? null));
                // Trace serveur pour vérification rapide
                @error_log('KYC Step3 payload for user '.$user->getId().': '.json_encode($payload));
                $em->persist($info);
                $em->flush();
            }

            if ($form->isSubmitted() && $form->isValid()) {
                $info = $user->getInfo();
                // Hydratation explicite des champs Step 3
                $safeGet = function(string $name) use ($form): int {
                    try { return (int) str_replace(' ', '', (string) ($form->has($name) ? ($form->get($name)->getData() ?? 0) : 0)); } catch (\Throwable $e) { return 0; }
                };
                $info->setSalary($safeGet('salary'));
                $info->setAccountSecurities($safeGet('accountSecurities'));
                // Récupérer sous-champs et calculer le total
                $getField = function(string $name) use ($form): int { try { return (int) str_replace(' ', '', (string) ($form->has($name) ? ($form->get($name)->getData() ?? 0) : 0)); } catch (\Throwable $e) { return 0; } };
                $info->setDepositSavingsChecking($getField('depositSavingsChecking'));
                $info->setDepositSavingsLivretA($getField('depositSavingsLivretA'));
                $info->setDepositSavingsLdd($getField('depositSavingsLdd'));
                $info->setDepositSavingsCsl($getField('depositSavingsCsl'));
                $info->setDepositSavingsOther($getField('depositSavingsOther'));
                $info->setDepositSavings($info->getDepositSavingsChecking() + $info->getDepositSavingsLivretA() + $info->getDepositSavingsLdd() + $info->getDepositSavingsCsl() + $info->getDepositSavingsOther());
                $info->setCapitalisation($safeGet('capitalisation'));
                $info->setScpi($safeGet('scpi'));
                $info->setRealestateIncome($safeGet('realestateIncome'));
                $getField = function(string $name) use ($form): int { try { return (int) str_replace(' ', '', (string) ($form->has($name) ? ($form->get($name)->getData() ?? 0) : 0)); } catch (\Throwable $e) { return 0; } };
                $info->setRealestatePrimaryResidence($getField('realestatePrimaryResidence'));
                $info->setRealestateInvestment($getField('realestateInvestment'));
                $info->setRealestate($info->getRealestatePrimaryResidence() + $info->getRealestateInvestment() + $info->getScpi());
                $info->setRent($safeGet('rent'));

                if (!$user->isPro()) {
                    $this->infoService->calculateUserFinancialValues($user);
                }
                if (!$user->hasRole('ROLE_USER_IDENTIFIED')) {
                    $user->setStepKyc(User::STEP_KYC_EXPERIENCE);
                }

                // Persister l'entité portée par le formulaire (Info ou Pro)
                if ($user->isPro()) {
                    $em->persist($user->getPro());
                } else {
                    $em->persist($info);
                }
                $em->persist($user);
                $em->flush();

                return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_EXPERIENCE]);
            }
        }
        
        return [
            'form' => $form->createView()
        ];

    }
}
