<?php

namespace App\Controller\Front\User\Steps;

use App\Entity\User\Info;
use App\Entity\User\User;
use App\Form\Front\User\Create\Step2Type;
use App\Services\User\InvestorProfileScorer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

trait Step2
{
    protected function step2(Request $request): array|RedirectResponse
    {
        $user = $this->getUser();
        // Trace requête Step2
        if (property_exists($this, 'logger') && $this->logger) {
            try { $this->logger->info('[KYC Step2][request]', ['method' => $request->getMethod(), 'uri' => $request->getRequestUri()]); } catch (\Throwable $e) {}
        }
        
        if (empty($user->getBirthplace()) and !$user->isPro()) {
            return $this->redirectToRoute('user_create_profile', ['step' => 1]);
        }
        if (!$user->getInfo()) {
            $info = new Info();
            $user->setInfo($info);
            // Assurer la liaison inverse pour éviter tout souci de persistance selon le contexte
            if (method_exists($info, 'setUser')) {
                $info->setUser($user);
            }
        }
        $form = $this->createForm(Step2Type::class, $user->getInfo());
        $em = $this->entityManager;
        $form->handleRequest($request);
        if (property_exists($this, 'logger') && $this->logger) {
            try { $this->logger->info('[KYC Step2][payload_raw]', ['post' => $request->request->all()]); } catch (\Throwable $e) {}
        }

        if ($form->isSubmitted()) {
            // Si c'est juste une sauvegarde temporaire
            if ($request->request->get('_save_only')) {
                // Hydrater depuis le payload puis sauvegarder immédiatement
                $formName = $form->getName();
                $payloadAll = $request->request->all();
                $payload = isset($payloadAll[$formName]) ? $payloadAll[$formName] : [];
                // Fallback clé directe
                if (empty($payload)) {
                    foreach (['objective','investmentTerm','liquidity','sourceOfFunds'] as $key) {
                        if (array_key_exists($key, $payloadAll)) { $payload[$key] = $payloadAll[$key]; }
                    }
                }
                $info = $user->getInfo();
                if ($info && !empty($payload)) {
                    $safeInt = static function($val): ?int {
                        if ($val === null || $val === '') { return null; }
                        $s = is_string($val) ? preg_replace('/[^\d]/', '', $val) : $val;
                        return ($s === '' || $s === null) ? null : (int) $s;
                    };
                    if (array_key_exists('objective', $payload)) {
                        $info->setObjective(is_array($payload['objective']) ? array_values($payload['objective']) : []);
                    }
                    if (array_key_exists('investmentTerm', $payload)) {
                        $info->setInvestmentTerm(is_array($payload['investmentTerm']) ? array_values($payload['investmentTerm']) : []);
                    }
                    if (array_key_exists('liquidity', $payload)) {
                        $info->setLiquidity($safeInt($payload['liquidity'] ?? null));
                    }
                    if (array_key_exists('sourceOfFunds', $payload)) {
                        $info->setSourceOfFunds(is_array($payload['sourceOfFunds']) ? array_values($payload['sourceOfFunds']) : []);
                    }
                    $em->persist($info);
                }
                $em->persist($user);
                $em->flush();
                // Redirection directe vers l'étape suivante
                return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_PATRIMONY]);
            }
            
            // Hydratation défensive à partir du POST, AVANT la validation (comme Step3)
            // Permet de sauvegarder même si une contrainte secondaire invalide le formulaire
            $formName = $form->getName();
            $payloadAll = $request->request->all();
            $payload = isset($payloadAll[$formName]) ? $payloadAll[$formName] : [];
            // Fallback si la clé de formulaire ne correspond pas (thèmes/form theming)
            if (empty($payload)) {
                // Chercher directement les clés attendues au premier niveau
                $fallback = [];
                foreach (['objective','investmentTerm','liquidity','sourceOfFunds'] as $key) {
                    if (array_key_exists($key, $payloadAll)) {
                        $fallback[$key] = $payloadAll[$key];
                    }
                }
                if (!empty($fallback)) {
                    $payload = $fallback;
                }
            }
            if (!empty($payload)) {
                $info = $user->getInfo();
                $safeInt = static function($val): ?int {
                    if ($val === null || $val === '') { return null; }
                    $s = is_string($val) ? preg_replace('/[^\d]/', '', $val) : $val;
                    $s = ($s === '' || $s === null) ? null : (int) $s;
                    return $s;
                };
                if (array_key_exists('objective', $payload)) {
                    $obj = $payload['objective'];
                    $info->setObjective(is_array($obj) ? array_values($obj) : []);
                }
                if (array_key_exists('investmentTerm', $payload)) {
                    $term = $payload['investmentTerm'];
                    $info->setInvestmentTerm(is_array($term) ? array_values($term) : []);
                }
                if (array_key_exists('liquidity', $payload)) {
                    $info->setLiquidity($safeInt($payload['liquidity']));
                }
                if (array_key_exists('sourceOfFunds', $payload)) {
                    $src = $payload['sourceOfFunds'];
                    $info->setSourceOfFunds(is_array($src) ? array_values($src) : []);
                }

                // Trace applicative si logger présent
                if (property_exists($this, 'logger') && $this->logger) {
                    try { $this->logger->info('[KYC Step2][fallback] payload', ['user_id' => $user->getId(), 'payload' => $payload]); } catch (\Throwable $e) {}
                }

                // Persist immédiat de l'info afin d'éviter toute perte
                $em->persist($info);
                $em->persist($user);
                $em->flush();
            }
            
            if ($form->isValid()) {
                $info = $user->getInfo();

                // Hydratation explicite des champs Step 2
                $info->setObjective($form->get('objective')->getData() ?? []);
                $info->setInvestmentTerm($form->get('investmentTerm')->getData() ?? []);
                $liquidity = $form->get('liquidity')->getData();
                $info->setLiquidity($liquidity !== null ? (int) str_replace(' ', '', $liquidity) : null);
                $info->setSourceOfFunds($form->get('sourceOfFunds')->getData() ?? []);
                // (les champs dépôt/épargne détaillés ont été déplacés à l'étape 3)

                // Forcer la persistance de l'entité Info si nouvellement créée
                $em->persist($info);

                // DEBUG/trace: consigner objectifs & horizon pour vérification
                $info = $user->getInfo();
                $objectives = is_array($info->getObjective()) ? implode(',', $info->getObjective()) : 'null';
                $terms = is_array($info->getInvestmentTerm()) ? implode(',', $info->getInvestmentTerm()) : 'null';
                error_log('[KYC Step2] user_id=' . $user->getId() . ' objectives=' . $objectives . ' investmentTerm=' . $terms);

                if (!$user->hasRole('ROLE_USER_IDENTIFIED')) {
                    $user->setStepKyc(User::STEP_KYC_PATRIMONY);
                }
                $em->persist($user);
                $em->flush();

                // Recalcul immédiat du profil
                if (property_exists($this, 'profileScorer') && $this->profileScorer instanceof InvestorProfileScorer) {
                    $this->profileScorer->calculateAndUpdateProfile($user);
                    $em->persist($user);
                    $em->flush();
                }

            return $this->redirectToRoute('user_create_profile', ['step' => User::STEP_KYC_PATRIMONY]);
            }
        }
        
        return [
            'form' => $form->createView()
        ];

    }
}
