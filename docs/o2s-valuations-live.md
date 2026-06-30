# O2S — Valorisations live & échanges Harvest

> **Dernière mise à jour** : 27 mai 2026
> **Statut** : 🟡 En attente de réponse Harvest (config "priorité Quantalys" sur tenant)
> **Documents liés** : [o2s-integration.md](./o2s-integration.md), [o2s-operations.md](./o2s-operations.md)

Ce document récapitule **tout le travail mené sur la problématique des valorisations live** des comptes O2S côté ADN Family Office : l'objectif, ce qui est implémenté aujourd'hui, ce qui manque, les données absentes, les échanges avec Harvest, et les décisions à prendre.

---

## Table des matières

1. [Objectif & contexte](#1-objectif--contexte)
2. [Architecture O2S confirmée par Harvest](#2-architecture-o2s-confirmée-par-harvest)
3. [Ce qu'on récupère aujourd'hui (implémentation actuelle)](#3-ce-quon-récupère-aujourdhui-implémentation-actuelle)
4. [Le problème observé : écart de date avec O2S Web](#4-le-problème-observé--écart-de-date-avec-o2s-web)
5. [Données manquantes (gaps identifiés)](#5-données-manquantes-gaps-identifiés)
6. [Endpoints API : ce qu'on a testé](#6-endpoints-api--ce-quon-a-testé)
7. [Échanges Harvest : chronologie](#7-échanges-harvest--chronologie)
8. [Questions toujours ouvertes](#8-questions-toujours-ouvertes)
9. [Options d'évolution](#9-options-dévolution)
10. [Recommandation & prochaines étapes](#10-recommandation--prochaines-étapes)
11. [Glossaire](#11-glossaire)

---

## 1. Objectif & contexte

### Objectif fonctionnel

Dans l'application ADN, afficher pour chaque client la valorisation **la plus à jour possible** de ses comptes financiers (assurance-vie, PER, PEA…), **avec un détail par support/fonds** identique à ce que le conseiller voit dans **O2S Web** (le backoffice Harvest).

### Périmètre

- ~300 clients ADN, ~480 comptes O2S, ~1800 ISIN distincts
- Comptes typiques : CNP Patrimoine, CNP Luxembourg, Generali Patrimoine, AXA Wealth, Nortia
- Affichage : dashboard client + page "Mes comptes" + calculs de PnL (Plus/Moins-Value)

### Contraintes

- Pas d'accès direct aux flux assureurs (uniquement via API O2S)
- Budget à optimiser (limiter les coûts d'abonnements externes)
- Hébergement OVH mutualisé (cf. [o2s-operations.md](./o2s-operations.md))

---

## 2. Architecture O2S confirmée par Harvest

**Source** : email d'Abdelhay TAHIRI (Harvest, Business Analyst) du **18 mai 2026**.

> *« Dans O2S, il faut distinguer deux notions : **la situation** et **la valorisation à date du jour**. »*

### 2.1 La "situation"

C'est l'état du compte (quantités de chaque support, lignes d'actifs) à un instant T figé.

- **Source** : flux assureur livré périodiquement à O2S (CNP, AXA, Generali, etc.)
- **Fréquence MAJ** : dépend de chaque assureur (souvent mensuelle ou bimensuelle)
- **Endpoint API** : `GET /accounts/{accountId}/account-details`
- **Champ date** : `referenceDate`
- **Particularité** : les **mouvements client** (versements, rachats, arbitrages) **n'impactent pas** la situation entre deux livraisons de flux. Ils sont stockés à part pour le calcul de performance.

### 2.2 La "valorisation à date du jour"

C'est le montant réel actuel du compte, calculé à la volée par O2S.

- **Formule** : `situation (quantités) × VL du jour (cours actuels)`
- **Source des VL** : principalement **Quantalys** (fournisseur de données marché Harvest), pour les supports ayant un ISIN. Pour les fonds euros (sans ISIN), valeur fournie directement par l'assureur.
- **Fréquence MAJ des VL** : J+1 ouvré avant 10h30
- **Endpoints API qui exposent cette valorisation totale** :
  - `GET /comptes/{compteId}` → champs `valeur.montant` et `valeur.dateValeur`
- **❌ Endpoint API qui n'expose PAS le détail par support en live** : aucun. C'est la limitation principale.

### 2.3 Schéma

```
┌─────────────────────────────────────────────────────────────┐
│                    Côté O2S (interne Harvest)               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Flux assureur (mensuel) ─▶  SITUATION (quantités figées) │
│                              │                              │
│                              ├──▶ /account-details API    │
│                              │     (snapshot historique)    │
│                              │                              │
│  Cours Quantalys (J+1) ──────┴──▶ VALORISATION J          │
│                                    │                        │
│                                    ├──▶ O2S Web (total + détail) │
│                                    └──▶ /comptes/{id} (total seul) │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Ce qu'on récupère aujourd'hui (implémentation actuelle)

### 3.1 Endpoints consommés

| Endpoint | Quand | Service | Notes |
|----------|-------|---------|-------|
| `POST /auth/realms/AppUsers/protocol/openid-connect/token` | À l'authentification (cache 24h) | `O2SAuthenticator` | OAuth2 password grant |
| `GET /contacts` | Sync incrémentale (15 min) | `ContactService` | Détection nouveaux contacts |
| `GET /contacts/{id}` | Sync détail contact | `ContactService` | Données nominatives |
| `GET /comptes?contactId={id}` | Sync comptes par contact | `CompteService` | Liste des contrats |
| `GET /comptes/{compteId}` | Sync valorisation | `CompteService::getCompte()` | **Valorisation totale live** (Quantalys-based) ✅ |
| `GET /accounts/{accountId}/account-details` | Sync détail actifs | `CompteService::getAccountDetails()` | **Snapshot situation figée** (date `referenceDate`) ⚠ |
| `GET /products/{id}` | Référentiel | `ProductService` | Types de contrats |
| `GET /institutions/{id}` | Référentiel | `InstitutionService` | Établissements |
| `GET /assets/{id}` | Référentiel | `AssetService` | Métadonnées fonds (label, ISIN, type) |

### 3.2 Stratégie actuelle de calcul de la valorisation

Code : `O2SSyncService::syncValuationsBatch()` (vers la ligne 845)

```
1. GET /comptes/{id}                    → récupère valeur.montant + valeur.dateValeur
   ├─ Si valeur.montant > 0 → on l'utilise (✅ valorisation live)
   └─ Sinon → fallback étape 2

2. GET /accounts/{id}/account-details   → récupère totalValue + liquidity
   ├─ Si totalValue OU liquidity → on additionne (snapshot figé)
   └─ Sinon → valorisation = NULL
```

**Stockage en base** : table `product_accounts`, colonnes `o2s_valuation` (montant) et `o2s_valuation_date` (date).

### 3.3 Données exposées dans le dashboard ADN

| Donnée affichée | Source | Fraîcheur |
|----------------|--------|-----------|
| Valorisation totale par compte | `o2s_valuation` (= `valeur.montant` de `/comptes`) | ✅ Live (alignée avec O2S Web) |
| Date de valorisation | `o2s_valuation_date` (= `valeur.dateValeur`) | ✅ Live |
| Détail par support (lignes d'actifs) | `getAccountDetails().situation[]` | ⚠ Snapshot figé (`referenceDate`) |
| PAM (prix moyen d'achat) | `averagePrice` de l'API + fallback `manual_pam_overrides` | ⚠ Partiel (cf. § 5) |

---

## 4. Le problème observé : écart de date avec O2S Web

### 4.1 Cas concret — Compte CNP de Philippe Corbeau

| Source | Date affichée | Montant |
|--------|---------------|---------|
| **O2S Web** (interface Harvest) | **05/05/2026** | **217 585,36 €** |
| `GET /comptes/{id}.valeur.dateValeur` | **05/05/2026** | **217 585,36 €** ✅ |
| `GET /accounts/{id}/account-details.referenceDate` | **15/04/2026** ⚠ | (correspond aux quantités, pas au montant live) |
| **Application ADN — bandeau total compte** | 05/05/2026 | 217 585,36 € ✅ |
| **Application ADN — détail par support** | 15/04/2026 ⚠ | Quantités × VL au 15/04 |

### 4.2 Pourquoi cet écart ?

Confirmation Harvest (mail 18/05) :
- `account-details` retourne **uniquement** le dernier snapshot reçu de l'assureur. Pour CNP Alysés Vie de M. Corbeau, le dernier snapshot CNP→O2S date du 15/04. **Tant que CNP ne livre pas un nouveau flux, le snapshot reste figé**.
- L'API **ne sait pas** générer un snapshot à la demande, ni recalculer à une date donnée.
- O2S Web combine en interne ce snapshot avec les VL Quantalys du jour pour afficher le détail. Cette opération **n'est pas exposée publiquement via l'API**.

### 4.3 Tests effectués pour contourner

| Tentative | Résultat |
|-----------|----------|
| `?date=2026-05-05` en query string sur `/account-details` | ❌ Paramètre ignoré |
| `?dateValeur=2026-05-05` | ❌ Paramètre ignoré |
| `?dateFrom=...&dateTo=...` | ⚠ Filtre les snapshots existants, ne déclenche pas de recalcul |
| `?view=SITUATION_VUE_O2S` | ❌ Selon Harvest, `view` n'est **pas** modifiable côté appelant (retourné en réponse uniquement) |
| `GET /accounts/{id}/account-details/{referenceDate}` (route avec date dans le path) | ✅ Existe officiellement, mais retourne uniquement les `referenceDate` déjà stockées |
| Endpoints alternatifs (`/assets/{id}/quote`, `/assets/{id}/nav`, `/quotes/{id}`) | ❌ N'existent pas dans la spec OpenAPI 1.90.7 |

### 4.4 Diagnostic technique disponible

Endpoint web de debug (temporaire, **à nettoyer** après résolution) :

- **Route** : `GET /cron/o2s-debug-raw?accountId={uuid}&view={view}&endpoint={alt_endpoint}&token={CRON_SECRET}`
- **Fichier** : `src/Controller/CronController.php`
- **Usage** : récupère le JSON brut de n'importe quel endpoint O2S pour un compte donné, sans transformation.

---

## 5. Données manquantes (gaps identifiés)

| Donnée manquante | Impact | Workaround actuel | Solution idéale |
|------------------|--------|-------------------|-----------------|
| **VL live par support/ISIN** | Détail des actifs figé à la `referenceDate` du dernier snapshot (parfois >1 mois) | Aucun (on affiche tel quel) | Souscription API Data Quantalys (~6 700 €/an) OU activation config Harvest qui rafraîchirait les snapshots |
| **PAM (Prix Moyen d'Achat)** sur certains comptes | PnL non calculable pour ces lignes | Table `manual_pam_overrides` + service `PamCalculationService` (cf. [o2s-integration.md](./o2s-integration.md)) | Harvest avait indiqué que certains prestataires ne fournissent pas le PAM → aucune solution côté API |
| **Génération de snapshots `referenceDate` plus fréquente** | Le snapshot reste parfois figé >1 mois (ex: CNP Corbeau 15/04 → toujours rien au 27/05) | Aucun (dépend du flux assureur) | À investiguer auprès de Harvest : pourquoi CNP ne livre-t-il pas plus fréquemment ? |
| **Historique de VL par ISIN** pour les graphiques d'évolution | Pas de graphique d'évolution par support disponible | Aucun | Quantalys API (option dispo dans leur offre, base 100 OST-adjusted) |
| **Forçage du calcul live côté serveur via API** | Obligation de passer par O2S Web pour voir une valo détaillée fraîche | Aucun | Demande explicite à Harvest d'exposer un endpoint (peu probable à court terme) |

---

## 6. Endpoints API : ce qu'on a testé

> 📚 Spécification officielle OpenAPI : `https://api.office2s.com/doc?format=json` (version 1.90.7 au 7 mai 2026)
> 📥 Téléchargements disponibles : [`/swagger/api-comptes.yml`](https://api.office2s.com/swagger/api-comptes.yml), PDF doc Comptes, collection Postman.

### 6.1 Endpoints qui répondent à nos besoins

| Endpoint | Réponse | Verdict |
|----------|---------|---------|
| `GET /comptes/{compteId}` | `valeur.montant` + `valeur.dateValeur` (calcul live via Quantalys) | ✅ Utilisé pour la valorisation totale |
| `GET /accounts/{id}/account-details` | `totalValue` + `liquidity` + `situation[]` du dernier snapshot | ✅ Utilisé pour la liste des actifs (fraîcheur dépendante du flux assureur) |
| `GET /comptes?contactId=...` | Liste des comptes d'un contact | ✅ Utilisé pour la sync |
| `GET /contacts` + `GET /contacts/{id}` | Données client | ✅ Utilisé pour la sync contacts |
| `GET /assets/{id}` | Métadonnées d'un asset (label, ISIN, type, currency) | ✅ Utilisé pour enrichir les libellés |

### 6.2 Endpoints qui n'aident pas

| Endpoint | Pourquoi pas utile |
|----------|---------------------|
| `GET /accounts/{id}/account-details/{referenceDate}` | Existe mais retourne uniquement des snapshots déjà stockés |
| `GET /assets` (collection) | Pas de cours / pas de VL, uniquement les métadonnées |

### 6.3 Endpoints inexistants (recherchés mais absents de la spec)

- `GET /assets/{id}/quote` ❌
- `GET /assets/{id}/nav` ❌
- `GET /quotes/{id}` ❌
- `GET /comptes/{id}/situation/live` ❌

**→ Conclusion** : l'API O2S **n'expose aucune route pour récupérer une VL live par ISIN**. C'est la limitation fondamentale qui justifierait un abonnement Quantalys API.

---

## 7. Échanges Harvest : chronologie

### 📧 20 avril 2026 — Notre demande initiale
**De** : Eric Boyer → Justine MOTUT (Harvest)
**Sujet** : Demande d'accès Quantalys pour calculs de valorisation API O2S

7 questions techniques sur l'API Data Quantalys (endpoint, auth, tarif, rate limits, couverture des fonds CNP/Generali/AXA, fréquence de MAJ des VL, alignement O2S Web ↔ Quantalys).

### 📧 21 avril 2026 — Réponses Quantalys
**De** : Justine MOTUT (Harvest, Ingénieure d'affaires)

Points clés :

1. **API Data Quantalys** = offre distincte des licences Quantalys, **GraphQL**, ~**6 700 € HT/an** + **2 000 € HT** de setup pour 1 800 ISIN. Doc : `https://webapi-stg.quantalys.com/DownloadCmd/GraphQLDevsDoc`
2. **Volumétrie** : jusqu'à 10 000 ISIN par appel, plusieurs appels/jour OK (mais MAJ une seule fois par jour)
3. **Couverture** : Quantalys couvre bien la majorité des fonds OPCVM/ETF distribués en France. Fonds euros = OK pour la plupart (mais sans ISIN propre, code interne assureur)
4. **Fréquence VL** : J+1 ouvré avant 10h30
5. **Historique VL** : disponible (base 100 OST-adjusted possible)
6. **🔑 Point critique** :
   > *« Il existe une configuration d'O2S pouvant 'forcer' O2S à utiliser en priorité les données Quantalys si l'ISIN en question est bien disponible dans la base Quantalys. Cette configuration (par client) peut être mise en place (si ce n'est pas déjà le cas). »*
7. **Limite** : alignement O2S ↔ Quantalys garanti uniquement pour les produits avec ISIN (donc **pas les fonds euros**)

### 📧 7 mai 2026 — Notre demande de précisions
**De** : Eric Boyer → Justine MOTUT
- Demande d'activation de la "priorité Quantalys" sur notre tenant
- Demande d'explication sur la fréquence de génération des snapshots `account-details`
- Constat de l'écart 15/04 vs 05/05 sur le compte CNP de M. Corbeau

### 📧 13 mai 2026 — Première réponse technique
**De** : Abdelhay TAHIRI (Harvest, Business Analyst)

Réponses **purement API** :
1. **`dateFrom`/`dateTo`** = filtres sur snapshots existants, **ne déclenchent aucun recalcul**
2. **`view: SITUATION_VUE_O2S`** = valeur **retournée** dans la réponse, **pas un paramètre d'entrée**. Non modifiable côté appelant.
3. **Snapshot figé** : *« L'API retourne ce qui est en base, elle ne crée pas de données. Si le dernier snapshot date du 15/04, c'est qu'aucune donnée plus récente n'existe pour ce compte dans O2S. »*

⚠ Cette dernière phrase était contradictoire avec le fait qu'O2S Web affichait du 05/05 → on a relancé.

### 📧 15 mai 2026 — Relance avec pointage des incohérences
**De** : Eric Boyer → Justine + Abdelhay

- Pointage des contradictions entre les deux mails Harvest
- Demande de **garantie écrite** que la combinaison `quantités snapshot O2S + VL Quantalys API` donnerait **exactement le même résultat** que O2S Web (avant de souscrire à 6 700 €/an)
- Relance sur la config "priorité Quantalys" (toujours sans réponse de Justine)

### 📧 18 mai 2026 — Clarification décisive
**De** : Abdelhay TAHIRI

> *« Dans O2S, il faut distinguer deux notions : la situation et la valorisation à date du jour. »*

C'est l'explication qui valide notre compréhension. Cf. [§ 2](#2-architecture-o2s-confirmée-par-harvest).

**Garantie écrite obtenue** :
> *« Si vous combinez les quantités du dernier snapshot account-details avec les VL du jour via l'API Quantalys, vous obtiendrez exactement la même valorisation que celle affichée dans O2S Web. »*

### 📧 18 mai 2026 (soir) — Notre relance ciblée
**De** : Eric Boyer → Justine MOTUT

- Remerciement pour la clarification d'Abdelhay
- **Relance à nouveau** sur la config "priorité Quantalys" (3ème demande)
- 3 questions précises :
  1. La config est-elle déjà active sur le tenant ADN ?
  2. Si non, quelle procédure pour l'activer ?
  3. La valorisation de `/comptes/{id}` utilise-t-elle déjà cette priorité ?

### 📭 27 mai 2026 — Toujours sans réponse de Justine

À relancer si pas de retour avant fin de semaine.

---

## 8. Questions toujours ouvertes

### Côté Harvest (à clarifier)

| # | Question | Cible | Statut |
|---|----------|-------|--------|
| 1 | La config "priorité Quantalys" est-elle active sur tenant ADN ? | Justine MOTUT | 🔴 Sans réponse depuis 3 relances |
| 2 | Si non active, procédure & coût d'activation ? | Justine MOTUT | 🔴 Sans réponse |
| 3 | Pourquoi le snapshot CNP Corbeau est-il figé au 15/04 depuis >1 mois ? Côté CNP ou côté config O2S ? | Abdelhay / Justine | 🟡 Réponse partielle (« c'est le flux assureur ») |
| 4 | L'activation Quantalys débloquerait-elle la génération de nouveaux snapshots ? | Justine | 🔴 Sans réponse |

### Côté ADN (à arbitrer)

| # | Décision à prendre | Échéance |
|---|---------------------|----------|
| A | Faut-il souscrire à l'API Data Quantalys (6 700 € HT/an + 2 000 € HT setup) ? | Après réponse Harvest q°1-4 |
| B | Faut-il afficher dans le dashboard une mention explicite « Détail au DD/MM/YYYY (valeur totale actualisée) » pour gérer l'écart de date ? | Court terme (1-2 sprints) |
| C | Faut-il développer un module client pour signaler à CNP/Generali les flux en retard ? | Selon retour Harvest q°3 |

---

## 9. Options d'évolution

### Option A — Statu quo (gratuit, peu satisfaisant)

- Garder l'implémentation actuelle (`o2s_valuation` = `valeur.montant` live)
- Afficher le détail par support tel qu'il est (snapshot figé)
- **Coût** : 0 €
- **Inconvénient** : décalage visible entre total compte (à jour) et détail des supports (parfois >1 mois en retard)
- **Verdict** : ❌ Insatisfaisant pour l'utilisateur

### Option B — Affichage hybride (gratuit, recommandé court terme)

- Conserver la valorisation totale via `/comptes/{id}` (déjà OK)
- Afficher le détail par support **avec mention explicite de la date du snapshot** (badge « Détail au 15/04/2026 »)
- **Coût** : 0 € (1-2 jours de dev front + petits ajustements back)
- **Avantage** : transparence pour le client, pas d'engagement financier
- **Verdict** : ✅ À implémenter **avant** toute décision Quantalys

### Option C — Souscription Quantalys API (payant, satisfaisant)

- Souscrire à l'API Data Quantalys (~6 700 € HT/an + 2 000 € HT setup, ~8 700 € HT première année, ~6 700 € HT/an récurrent, indexé Syntec)
- Récupérer les VL du jour pour chaque ISIN détenu par nos clients
- Recalculer côté ADN : `valeur = quantité (snapshot O2S) × VL du jour (Quantalys)`
- Afficher un détail par support 100 % aligné avec O2S Web
- **Garantie Harvest** ✅ (mail 18/05)
- **Limite** : fonds euros sans ISIN restent sur la valeur snapshot (mais c'est aussi le cas dans O2S Web)
- **Verdict** : ✅ À évaluer **après** retour Harvest sur la priorité Quantalys (peut-être inutile si déjà active côté tenant)

### Option D — Tenter d'obtenir gratuitement de Harvest (à explorer)

- Si la config "priorité Quantalys" est activable côté tenant **sans coût additionnel**, elle pourrait suffire à débloquer le rafraîchissement des snapshots
- **Coût** : 0 € (sous réserve de confirmation Harvest)
- **Verdict** : ⏳ Dépend de la réponse pendante de Justine

---

## 10. Recommandation & prochaines étapes

### Court terme (1-2 sprints)

1. **Relancer Justine MOTUT** sur les questions 1-4 (§ 8). Échéance : avant fin mai 2026
2. **Implémenter l'Option B** (badge date du snapshot dans le dashboard détail des supports)
3. **Nettoyer l'endpoint de debug** `/cron/o2s-debug-raw` une fois le sujet stabilisé

### Moyen terme (sous réserve de retour Harvest)

4. **Si la priorité Quantalys est activable gratuitement** → l'activer, mesurer l'impact sur les snapshots pendant 1 mois
5. **Si elle ne suffit pas / déjà active** → arbitrer la souscription Quantalys API (Option C) avec un argumentaire chiffré pour Philippe & Eudes

### Long terme

6. Si l'Option C est retenue → développer le module de calcul live `quantités × VL Quantalys` côté ADN
7. Ajouter les graphiques d'évolution par support (historique Quantalys base 100 OST-adjusted)

---

## 11. Glossaire

| Terme | Définition |
|-------|------------|
| **O2S** | Office2S, plateforme Harvest de gestion de patrimoine pour CGP. API : `api.office2s.com` |
| **O2S Web** | Interface backoffice Harvest (`*.office2s.com`) — pas une API, vue HTML pour conseiller |
| **Quantalys** | Société du groupe Harvest, fournisseur de données marché (cours, VL, performances) |
| **Situation** | État du compte (quantités, lignes d'actifs) à une `referenceDate` donnée, mise à jour par flux assureur |
| **Valorisation à date du jour** | Montant réel = situation × VL Quantalys du jour, calculée à la volée par O2S Web et `/comptes/{id}` |
| **VL** | Valeur Liquidative (cours d'un fonds, OPCVM, ETF) |
| **ISIN** | International Securities Identification Number (identifiant unique d'un actif financier) |
| **PAM** | Prix Moyen d'Achat (cf. `manual_pam_overrides` dans [o2s-integration.md](./o2s-integration.md)) |
| **PnL** | Plus/Moins-Value latente = `valorisation - (quantité × PAM)` |
| **Snapshot** / **`referenceDate`** | Photo de la situation à une date donnée, persistée côté O2S, exposée via `/account-details` |
| **Priorité Quantalys** | Configuration tenant O2S permettant de forcer l'usage de Quantalys comme source de VL prioritaire pour les ISIN couverts |
| **Tenant** | Instance O2S dédiée à un cabinet (ici ADN Family Office) |
| **Fonds euros** | Support assurance-vie en euros (capital garanti). Pas d'ISIN, code interne par assureur. |

---

## Contacts Harvest

| Personne | Rôle | Email |
|----------|------|-------|
| Justine MOTUT | Ingénieure d'affaires (commercial / config tenant) | justine.motut@harvest.fr |
| Abdelhay TAHIRI | Business Analyst (technique API) | abdelhay.tahiri@harvest.fr |
| Gallien GIRARDOT | Contact Harvest | gallien.girardot@harvest.fr |
| Support API O2S | Assistance technique générale | assist-api@o2s.harvest.fr |
