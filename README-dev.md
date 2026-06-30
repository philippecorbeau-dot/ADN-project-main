# Documentation Développeur - ADN Project

## Système de Scoring du Profil Investisseur

### Vue d'ensemble

Le système de scoring du profil investisseur calcule automatiquement un score cumulé basé sur les réponses du parcours KYC (Steps 2-4) et du questionnaire de connaissance des marchés financiers.

### Catégories de Profil

- **PRUDENT** (0-29 points) : Investisseur préférant les placements sécurisés
- **ÉQUILIBRÉ** (30-59 points) : Approche prudente mais diversifiée
- **DYNAMIQUE** (60-79 points) : Bonne connaissance des marchés, appétence pour le risque modéré à élevé
- **SPE** (80+ points) : Investisseur Sophistiqué avec expérience avancée

### Calcul du Score

#### Step 2 - Objectifs d'investissement
- Crowdfunding immobilier : +10 points
- Investissement locatif : +15 points
- SCPI : +12 points
- Je ne sais pas encore : +5 points

#### Step 3 - Patrimoine/Situation financière
- Patrimoine immobilier : 5-20 points selon le montant
- Patrimoine financier : 5-25 points selon le montant
- Revenus : 5-15 points selon le montant

#### Step 4 - Expérience
- Critères d'awareness : +10 points chacun
- Statut MIF2 : +25 points
- Profil aware : +15 points

#### Questionnaire de connaissance
- Produits financiers : 0-24 points
- Produits complexes : 0-30 points
- Expérience des marchés : 0-20 points
- Bonus éducation : 5-10 points

### Composants Techniques

#### Service InvestorProfileScorer
- `calculateTotalScore()` : Calcule le score cumulé
- `calculateProfileType()` : Détermine la catégorie
- `calculateAndUpdateProfile()` : Met à jour l'entité User
- `getProductRecommendations()` : Recommandations par profil

#### Listener KycScoringSubscriber
- Déclenche le recalcul automatique après chaque step KYC
- Vérifie les conditions de recalcul (profil null ou ancien)
- Gestion d'erreurs sans interruption du processus

#### Back-office EasyAdmin
- Section "Profil investisseur" avec filtres et recherche
- Action "Recalculer le profil" avec confirmation
- Template détaillé avec scoring par étape

### Utilisation

#### Recalcul automatique
Le profil est recalculé automatiquement après chaque soumission de formulaire KYC.

#### Recalcul manuel
```php
$profileScorer->calculateAndUpdateProfile($user);
$entityManager->persist($user);
$entityManager->flush();
```

#### Accès aux données
```php
$score = $user->getInvestorScore();
$profile = $user->getInvestorProfile();
$calculatedAt = $user->getInvestorProfileCalculatedAt();
```

### Tests

Exécuter les tests unitaires :
```bash
php bin/phpunit tests/Services/User/InvestorProfileScorerTest.php
```

### Migration

Les champs de scoring ont été ajoutés à l'entité User :
- `investorScore` (int, nullable)
- `investorProfile` (string, nullable)
- `investorProfileCalculatedAt` (datetime, nullable)

Migration appliquée : `Version20250804235026` 