# Intégration O2S (Harvest) — Documentation technique

> **Dernière mise à jour** : Février 2026  
> **Branche** : `develop`
>
> 📌 **Documents complémentaires** :
> - [`o2s-operations.md`](./o2s-operations.md) — Guide opérationnel (commandes, cron, dépannage)
> - [`o2s-valuations-live.md`](./o2s-valuations-live.md) — Valorisations live, échanges Harvest, questions ouvertes

## Table des matières

1. [Vue d'ensemble](#1-vue-densemble)
2. [Architecture](#2-architecture)
3. [Authentification](#3-authentification)
4. [Endpoints API O2S utilisés](#4-endpoints-api-o2s-utilisés)
5. [DTOs (Data Transfer Objects)](#5-dtos-data-transfer-objects)
6. [Services](#6-services)
7. [Synchronisation](#7-synchronisation)
8. [Catégorisation des comptes](#8-catégorisation-des-comptes)
9. [Gestion des doublons](#9-gestion-des-doublons)
10. [Interface d'administration](#10-interface-dadministration)
11. [Schéma de la base de données](#11-schéma-de-la-base-de-données)

---

## 1. Vue d'ensemble

L'intégration O2S connecte ADN Family Office à **Harvest O2S** (Office2S), la plateforme de gestion de patrimoine utilisée par les CGP (Conseillers en Gestion de Patrimoine).

**Flux de données** : O2S → ADN (synchronisation unidirectionnelle)

```
┌─────────────┐     API REST      ┌──────────────┐     Doctrine      ┌────────────┐
│  Harvest O2S │ ───────────────▶ │  ADN Backend  │ ───────────────▶ │  MySQL DB  │
│  (source)    │  JSON / OAuth2   │  (Symfony)    │                  │  (local)   │
└─────────────┘                   └──────────────┘                   └────────────┘
```

**Données synchronisées** :
- **Contacts** → `users_adn` (clients du CGP)
- **Comptes** → `product_accounts` (contrats financiers)
- **Valorisations** → champs `o2s_valuation` / `o2s_valuation_date` dans `product_accounts`

---

## 2. Architecture

### Arborescence des fichiers

```
src/Integration/O2S/
├── Client/
│   ├── O2SAuthenticator.php          # Authentification OAuth2
│   ├── O2SAuthenticatorInterface.php
│   ├── O2SClient.php                 # Client HTTP principal
│   └── O2SClientInterface.php
├── Config/
│   └── O2SConfiguration.php          # Configuration (env vars)
├── DTO/
│   ├── Auth/
│   │   └── TokenDTO.php              # Token OAuth2
│   ├── Compte/
│   │   ├── AccountDetailsDTO.php     # Détails valorisation (totalValue, liquidity)
│   │   ├── AssetLineDTO.php          # Ligne d'actif (ISIN, quantité, VL)
│   │   ├── CompteDTO.php             # Compte/contrat O2S
│   │   └── DetenteurDTO.php          # Détenteur d'un compte
│   ├── Contact/
│   │   ├── AddressDTO.php            # Adresse postale
│   │   └── ContactDTO.php            # Contact (client)
│   ├── Institution/
│   │   └── InstitutionDTO.php        # Établissement financier
│   └── Product/
│       └── ProductDTO.php            # Type de produit (contrat)
├── Exception/
│   ├── O2SApiException.php           # Erreurs API
│   ├── O2SAuthenticationException.php # Erreurs auth
│   └── O2SException.php              # Exception de base
├── O2SManager.php                    # Façade principale
├── Service/
│   ├── AssetService.php              # API /assets
│   ├── CompteService.php             # API /comptes + /accounts
│   ├── ContactService.php            # API /contacts
│   ├── InstitutionService.php        # API /institutions
│   └── ProductService.php            # API /products
└── Sync/
    ├── O2SSyncService.php            # Service de synchronisation principal
    ├── ProductSyncService.php        # Sync comptes → ProductAccount
    ├── SyncResult.php                # Objet résultat de sync
    └── UserSyncService.php           # Sync contacts → User
```

### Commandes Symfony

```
src/Command/
├── O2SSyncCommand.php                # Sync complète (contacts + comptes)
├── O2SSyncIncrementalCommand.php     # Sync incrémentale + valorisations
├── O2SMergeDuplicatesCommand.php     # Fusion des doublons
├── O2STestCommand.php                # Tests API manuels
└── O2SLinkUserCommand.php            # Lier un utilisateur manuellement
```

### Contrôleur Admin

```
src/Controller/Admin/O2SAdminController.php  # Interface admin /admin/o2s
```

---

## 3. Authentification

### Protocole

O2S utilise **OAuth2 Resource Owner Password Credentials** (grant_type=password).

### Configuration

Variables d'environnement requises dans `.env.local` :

```env
O2S_CLIENT_ID=votre_client_id
O2S_CLIENT_SECRET=votre_client_secret
O2S_USERNAME=votre_username
O2S_PASSWORD=votre_password
O2S_ENVIRONMENT=production    # ou 'recette' pour l'environnement de test
```

### URLs d'authentification

| Environnement | URL Auth |
|---------------|----------|
| Production    | `https://auth.harvest.fr` |
| Recette       | `https://auth-r7.harvest.fr` |

### Endpoint token

```
POST {AUTH_URL}/auth/realms/AppUsers/protocol/openid-connect/token

Content-Type: application/x-www-form-urlencoded

grant_type=password
&client_id={O2S_CLIENT_ID}
&client_secret={O2S_CLIENT_SECRET}
&username={O2S_USERNAME}
&password={O2S_PASSWORD}
```

### Réponse

```json
{
  "access_token": "eyJhbG...",
  "refresh_token": "eyJhbG...",
  "expires_in": 86400,
  "refresh_expires_in": 172800,
  "token_type": "bearer"
}
```

### Gestion du cache

Les tokens sont mis en cache dans le système de fichiers Symfony (`var/cache/o2s_auth/`) pour éviter de ré-authentifier à chaque requête.

- **Durée de vie du cache** : `expires_in` secondes (24h en production)
- **Marge de sécurité** : 60 secondes retirées avant expiration
- **Refresh automatique** : Si le token expire mais que le refresh token est valide, un refresh est tenté avant une ré-authentification complète

Classe : `O2SAuthenticator` → méthodes `getToken()`, `authenticate()`, `refresh()`

---

## 4. Endpoints API O2S utilisés

**URL de base** : `https://api.office2s.com`

### Contacts

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/contacts` | Liste des contacts (paginé : `limit`, `offset`) |
| `GET` | `/contacts/{id}` | Détail d'un contact |
| `POST` | `/contacts` | Créer un contact |
| `PUT` | `/contacts/{id}` | Modifier un contact |

**Paramètres de pagination** : `?limit=100&offset=0`

**Réponse type** (GET /contacts/{id}) :

```json
{
  "id": "abc-123-def",
  "personne": {
    "donneesNominatives": {
      "civilite": "M",
      "nom": "DUPONT",
      "prenoms": ["Jean"]
    },
    "moyensContact": {
      "emails": [{ "adresse": "jean@email.com" }],
      "telephones": [{ "numero": "0612345678", "type": "MOBILE" }],
      "adresse": {
        "voie": "12 rue de la Paix",
        "codePostal": "75001",
        "localite": "Paris",
        "codePays": "FR"
      }
    },
    "naissance": {
      "dateNaissance": "1975-03-15",
      "lieuNaissance": "Paris"
    }
  },
  "dateCreation": "2024-01-15T10:30:00Z",
  "dateMaj": "2025-06-20T14:00:00Z"
}
```

### Comptes

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/comptes` | Liste des comptes (filtrage par `contactId`) |
| `GET` | `/comptes/{id}` | Détail complet d'un compte |
| `POST` | `/comptes` | Créer un compte |
| `PUT` | `/comptes/{id}` | Modifier un compte |

**Réponse type** (GET /comptes/{id}) :

```json
{
  "id": "xyz-789",
  "libelle": "AV Cardif Multi Plus 3i",
  "type": "COMPTE",
  "modeleFinancier": "ASSURANCE_UC",
  "identification": {
    "numero": "12345678"
  },
  "produitLie": {
    "produitId": "prod-456"
  },
  "placement": {
    "statut": "ACTIF",
    "dateOuverture": "2020-05-10"
  },
  "valeur": {
    "montant": 125000.50,
    "devise": "EUR",
    "dateValeur": "2026-02-15"
  },
  "detenteurs": {
    "detenteurs": [
      { "contactId": "abc-123-def", "role": "SOUSCRIPTEUR" }
    ]
  }
}
```

### Account Details (Valorisations détaillées)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/accounts/{id}/account-details` | Valorisation détaillée + actifs |

> **Important** : Cet endpoint utilise `/accounts/` (pas `/comptes/`), mais avec le même ID.

**Réponse type** :

```json
{
  "totalValue": 125000.50,
  "liquidity": 0,
  "referenceDate": "2026-02-15",
  "situation": [
    {
      "assetId": "asset-001",
      "assetName": "Fonds Euros Cardif",
      "isin": "FR0000000001",
      "quantity": 100.00,
      "netAssetValue": 35.20,
      "netAssetValueDate": "2026-02-14",
      "value": 3520.00,
      "assetType": "FONDS_EUROS"
    }
  ]
}
```

> **Cas particulier des comptes agrégés** (Livret, Compte Courant, PEL…) :
> - `totalValue` = `null` ou `0`
> - `liquidity` = le solde réel (ex: `15 000 €`)
> - Notre logique : priorise `totalValue` > `liquidity` > `montant` (de `/comptes/{id}`)

### Produits (Types de contrats)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/products` | Liste des types de produits |
| `GET` | `/products/{id}` | Détail d'un produit |

### Institutions (Établissements financiers)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/institutions` | Liste des établissements |
| `GET` | `/institutions/{id}` | Détail d'un établissement |

### Assets (Actifs financiers)

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `GET` | `/assets/{id}` | Détail d'un actif (fonds, OPCVM…) |

---

## 5. DTOs (Data Transfer Objects)

Tous les DTOs sont **immutables** (`readonly`) et possèdent une factory method `fromApiResponse(array $data)`.

### ContactDTO

| Champ | Type | Source API |
|-------|------|------------|
| `id` | `string` | `id` |
| `civilite` | `?string` | `personne.donneesNominatives.civilite` |
| `nom` | `?string` | `personne.donneesNominatives.nom` |
| `prenoms` | `string[]` | `personne.donneesNominatives.prenoms` |
| `email` | `?string` | `personne.moyensContact.emails[0].adresse` |
| `telephone` | `?string` | `personne.moyensContact.telephones` (non mobile) |
| `telephoneMobile` | `?string` | `personne.moyensContact.telephones` (type=MOBILE) |
| `dateNaissance` | `?DateTimeImmutable` | `personne.naissance.dateNaissance` |
| `adresse` | `?AddressDTO` | `personne.moyensContact.adresse` |

### CompteDTO

| Champ | Type | Source API |
|-------|------|------------|
| `id` | `string` | `id` |
| `libelle` | `?string` | `libelle` |
| `numero` | `?string` | `identification.numero` |
| `modeleFinancier` | `?string` | `modeleFinancier` |
| `produitId` | `?string` | `produitLie.produitId` |
| `statut` | `?string` | `placement.statut` |
| `montant` | `?float` | `valeur.montant` |
| `dateValeur` | `?DateTimeImmutable` | `valeur.dateValeur` |
| `detenteurs` | `DetenteurDTO[]` | `detenteurs.detenteurs` |

**Méthode importante** : `getProductType()` — mappe `modeleFinancier` vers les types ADN.

### AccountDetailsDTO

| Champ | Type | Description |
|-------|------|-------------|
| `accountId` | `string` | ID du compte |
| `totalValue` | `?float` | Valorisation totale (produits gérés) |
| `liquidity` | `?float` | Liquidités / solde (comptes bancaires) |
| `situation` | `AssetLineDTO[]` | Liste des actifs |
| `valuationDate` | `?DateTimeImmutable` | Date de la valorisation |

---

## 6. Services

### O2SClient

Client HTTP central. Toutes les requêtes passent par ce service.

- Ajout automatique du header `Authorization: Bearer {token}`
- Gestion des erreurs HTTP (401, 404, 429…)
- Pagination automatique via `getPaginated()`

### CompteService

Service principal pour les comptes. Méthodes clés :

| Méthode | Description |
|---------|-------------|
| `getCompte($id)` | Récupère un compte complet |
| `getComptesForContact($contactId)` | Tous les comptes d'un contact |
| `getActiveComptesForContact($contactId)` | Comptes actifs uniquement |
| `getAccountDetails($id)` | Valorisation détaillée (totalValue + liquidity) |
| `calculateSummary($comptes)` | Statistiques agrégées par type |

### ContactService

| Méthode | Description |
|---------|-------------|
| `getContact($id)` | Récupère un contact |
| `getAllContacts()` | Tous les contacts (paginé en interne) |
| `findByEmail($email)` | Recherche par email |

### O2SManager

**Façade** : point d'entrée unique pour le code applicatif.

```php
// Utilisation recommandée dans les contrôleurs :
$this->o2sManager->fullSync($user);
$this->o2sManager->getPortfolioSummary($user);
$this->o2sManager->refreshProductValuation($productAccount);
```

---

## 7. Synchronisation

### Vue d'ensemble des flux

```
┌──────────────────────────────────────────────────────────────────┐
│                    Flux de synchronisation                        │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Sync Contacts   ─▶  O2S Contact  ──▶  User (users_adn)     │
│     (syncAllContacts)    ↓ matching:                             │
│                          - o2s_contact_id (exact)                │
│                          - email (exact)                         │
│                          - nom+prénom (insensible casse/espaces) │
│                                                                  │
│  2. Sync Comptes    ─▶  O2S Compte   ──▶  ProductAccount        │
│     (syncAllComptes)     par user lié, fetch détail individuel   │
│                                                                  │
│  3. Sync Valorisations  ─▶  /accounts/{id}/account-details      │
│     (syncValuationsBatch)   totalValue || liquidity || montant   │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

### O2SSyncService — Méthodes principales

#### `syncAllContacts()`
Synchronise **tous** les contacts O2S avec les utilisateurs locaux.

**Algorithme de matching** (dans l'ordre) :
1. **Par `o2sContactId`** : correspondance exacte (utilisateur déjà lié)
2. **Par email** : correspondance exacte, uniquement si l'utilisateur n'est pas déjà lié
3. **Par nom + prénom** : insensible à la casse, ignore les espaces et tirets, uniquement si un seul candidat non lié

Si aucun match → **création** d'un utilisateur placeholder avec email `o2s_{contactId}@placeholder.local`.

#### `syncAllComptes()`
Pour chaque utilisateur lié à O2S :
1. Récupère la liste des comptes via `/comptes?contactId={id}`
2. Pour chaque compte, récupère le détail via `/comptes/{id}`
3. Crée ou met à jour le `ProductAccount` local

#### `syncValuationsBatch(int $batchSize = 50)`
Met à jour les valorisations par lots. **Priorisation** :
1. Comptes avec valorisation `NULL` ou `0` (en premier)
2. Comptes avec la date de sync la plus ancienne

**Logique de récupération de la valorisation** :
```
1. GET /comptes/{id}         → montant (dans valeur.montant)
2. Si montant = 0 :
   GET /accounts/{id}/account-details  → totalValue ou liquidity
3. Priorité : totalValue > liquidity > montant
```

> **Pourquoi `liquidity` ?**  
> Les comptes agrégés (Livret A, Compte Courant, PEL…) n'ont pas de `totalValue` car ils ne contiennent pas d'actifs financiers. Leur solde est dans le champ `liquidity`.

#### `syncNewContacts()` (incrémental)
Sync rapide (~10s) pour le cron toutes les 15 min :
1. Récupère tous les contacts O2S
2. Compare avec les `o2sContactId` existants en local
3. Ne synchronise que les **nouveaux** contacts

#### `syncComptesBatch(int $offset, int $batchSize)`
Sync des comptes par lot, conçu pour les appels AJAX séquentiels afin d'éviter les timeouts sur hébergement mutualisé OVH.

---

## 8. Catégorisation des comptes

Les comptes O2S sont classés en 3 catégories selon leur `product_type` :

### 💼 Gestion directe (`gere`)
Comptes gérés activement par le CGP.

| Type local | Modèle financier O2S |
|------------|---------------------|
| `ASSURANCE_VIE` | `ASSURANCE_UC`, `ASSURANCE_EURO`, `ASSURANCE_EURO_CROISSANCE` |
| `PER` | `PER`, `PERIN_ASSURANTIEL`, `PERIN_COMPTE_TITRES` |
| `PEA` | `PEA`, `PEA_NUMERAIRE` |
| `PEA_PME` | `PEA_PME`, `PEA_PME_NUMERAIRE` |
| `SCPI` | `SCPI`, `PART_SCI_GIRARDIN` |
| `COMPTE_TITRES` | `COMPTE_TITRE`, `CTO` |
| `CAPITALISATION` | `BON_CAPI_UC`, `BON_CAPI_EURO` |
| `RETRAITE_ENTREPRISE` | `RETRAITE_ENTREPRISE` |
| `MADELIN` | `MADELIN`, `MADELIN_UC` |
| `DEFISCALISATION` | `PART_SNC_GIRARDIN`, `PART_GF`, `COMPTE_DEFISCALISATION` |
| `AUTRE` | Types non reconnus |

### 🏢 Épargne salariale (`epargne`)
Comptes d'épargne entreprise.

| Type local | Modèle financier O2S |
|------------|---------------------|
| `EPARGNE_SALARIALE` | `PEE`, `PEI`, `EPARGNE_SALARIALE` |
| `PERCO` | `PERCO` |

### 🏦 Comptes agrégés (`agrege`)
Comptes bancaires agrégés depuis les banques des clients.

| Type local | Modèle financier O2S |
|------------|---------------------|
| `COMPTE_COURANT` | `COMPTE_COURANT`, `CPT_ESPECES` |
| `LIVRET` | `LIVRET`, `LIVRET_A`, `LDDS`, `LEP`, `LIVRET_JEUNE` |
| `EPARGNE_LOGEMENT` | `CEL`, `PEL` |
| `COMPTE_A_TERME` | `COMPTE_A_TERME` |

> **Note** : L'**Encours géré** affiché sur O2S correspond uniquement à la catégorie "Gestion directe".

---

## 9. Gestion des doublons

### Origine du problème

Les doublons apparaissent quand :
1. Un client est créé manuellement sur ADN (inscription KYC)
2. Le même client est synchronisé depuis O2S → crée un utilisateur `o2s_xxx@placeholder.local`

### Détection

Les doublons sont détectés par correspondance nom + prénom, en ignorant casse, espaces et tirets :

```sql
LOWER(REPLACE(REPLACE(TRIM(a.last_name), ' ', ''), '-', ''))
= LOWER(REPLACE(REPLACE(TRIM(b.last_name), ' ', ''), '-', ''))
```

**Critères** :
- `a` = utilisateur **sans** `o2s_contact_id` (manuel)
- `b` = utilisateur **avec** `o2s_contact_id` et email `o2s_%@placeholder.local` (placeholder O2S)

### Fusion

La fusion :
1. Transfère `o2s_contact_id` → utilisateur manuel (si pas déjà lié)
2. Transfère tous les `product_accounts` → utilisateur manuel
3. Supprime l'utilisateur placeholder O2S

### Prévention

Lors de la sync des contacts, le matching par nom+prénom empêche la création de nouveaux placeholders si un utilisateur manuel correspondant existe déjà.

---

## 10. Interface d'administration

**URL** : `/admin/o2s`  
**Accès** : `ROLE_ADMIN` requis

### Pages

| Route | Description |
|-------|-------------|
| `/admin/o2s` | Dashboard principal (statistiques, catégories, boutons sync) |
| `/admin/o2s/contacts` | Liste des utilisateurs (liés/non liés O2S) |
| `/admin/o2s/contact/{id}` | Détail d'un contact O2S avec ses comptes |
| `/admin/o2s/comptes` | Liste de tous les comptes O2S avec valorisations |
| `/admin/o2s/compte/{id}` | Détail d'un compte avec ses actifs |
| `/admin/o2s/produits` | Comptes synchronisés (vue paginée) |
| `/admin/o2s/institutions` | Établissements financiers O2S |
| `/admin/o2s/o2s-products` | Types de produits O2S |
| `/admin/o2s/duplicates` | Gestion des doublons |

### Boutons d'action (Dashboard)

| Bouton | Route AJAX | Description |
|--------|------------|-------------|
| **Sync rapide** | `POST /admin/o2s/sync/incremental` | Détecte les nouveaux contacts (~10s) |
| **MAJ Valorisations** | `POST /admin/o2s/sync/valuations` | Met à jour 30 comptes par lot |
| **Sync complète** | `POST /admin/o2s/sync/contacts` puis `/sync/comptes` (boucle AJAX) | Resynchronise tout (~5-15 min) |

### Protection CSRF

Toutes les actions POST utilisent un token CSRF `o2s_sync` :

```javascript
const CSRF_TOKEN = '{{ csrf_token("o2s_sync") }}';
```

---

## 11. Schéma de la base de données

### Table `users_adn` (champs O2S)

| Colonne | Type | Description |
|---------|------|-------------|
| `o2s_contact_id` | `VARCHAR(255) NULL` | ID du contact O2S (lien unique) |
| `o2s_type_contact` | `VARCHAR(20) NULL` | Classification Harvest : `Client` ou `Prospect` |
| `o2s_synced_at` | `DATETIME NULL` | Date de dernière synchronisation |

### Table `product_accounts` (champs O2S)

| Colonne | Type | Description |
|---------|------|-------------|
| `o2s_compte_id` | `VARCHAR(255) NULL` | ID du compte O2S |
| `o2s_valuation` | `DECIMAL NULL` | Valorisation récupérée depuis O2S |
| `o2s_valuation_date` | `DATE NULL` | Date de la valorisation |
| `o2s_synced_at` | `DATETIME NULL` | Date de dernière synchronisation |
| `product_type` | `VARCHAR(50)` | Type de produit (ASSURANCE_VIE, PER, LIVRET…) |
| `display_alias` | `VARCHAR(255)` | Nom d'affichage (libellé O2S) |
| `internal_name` | `VARCHAR(255)` | Nom interne (numéro de contrat) |
| `distributor` | `VARCHAR(255)` | Distributeur (« O2S - Harvest ») |

### Migration Doctrine

Fichier : `migrations/Version20260203_O2SIntegration.php`



