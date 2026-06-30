# O2S — Guide d'exploitation et opérations

> **Dernière mise à jour** : Février 2026  
> Guide opérationnel pour l'administration quotidienne de l'intégration O2S.
>
> 📌 **Documents complémentaires** :
> - [`o2s-integration.md`](./o2s-integration.md) — Documentation technique de l'intégration
> - [`o2s-valuations-live.md`](./o2s-valuations-live.md) — Valorisations live, échanges Harvest, questions ouvertes

## Table des matières

1. [Commandes Symfony](#1-commandes-symfony)
2. [Tâches planifiées (Cron)](#2-tâches-planifiées-cron)
3. [Interface admin](#3-interface-admin)
4. [Dépannage](#4-dépannage)
5. [Déploiement](#5-déploiement)
6. [Monitoring](#6-monitoring)

---

## 1. Commandes Symfony

### `o2s:sync-incremental` — Synchronisation incrémentale

Commande principale pour le cron. Rapide (~10 secondes).

```bash
# Sync incrémentale (nouveaux contacts + comptes manquants)
php bin/console o2s:sync-incremental

# Mise à jour des valorisations (50 comptes par lot)
php bin/console o2s:sync-incremental --valuations

# Idem avec lot personnalisé (100 comptes)
php bin/console o2s:sync-incremental --valuations --batch-size=100

# Sync des comptes manquants uniquement
php bin/console o2s:sync-incremental --missing-comptes

# Sync contacts uniquement (sans comptes)
php bin/console o2s:sync-incremental --contacts-only
```

**Options** :

| Option | Alias | Description | Défaut |
|--------|-------|-------------|--------|
| `--valuations` | | Met à jour les valorisations par lot | false |
| `--contacts-only` | | Nouveaux contacts uniquement | false |
| `--missing-comptes` | | Comptes manquants uniquement | false |
| `--batch-size` | `-b` | Nombre d'éléments par lot | 50 |

### `o2s:sync` — Synchronisation complète

Resynchronise **tout** depuis O2S. Long (~10-15 minutes).

```bash
# Sync contacts + comptes
php bin/console o2s:sync --comptes

# En production (sans debug, logs minimaux)
php bin/console o2s:sync --comptes --env=prod --no-debug
```

### `o2s:merge-duplicates` — Fusion des doublons

Détecte et fusionne les utilisateurs en doublon (manuel ↔ placeholder O2S).

```bash
# Mode simulation (affiche sans modifier)
php bin/console o2s:merge-duplicates --dry-run

# Exécution avec confirmation interactive
php bin/console o2s:merge-duplicates

# Exécution directe sans confirmation
php bin/console o2s:merge-duplicates --force
```

### `o2s:test` — Tests API manuels

Utile pour débugger les connexions API.

```bash
# Test de connexion
php bin/console o2s:test

# Récupérer les détails d'un compte
php bin/console o2s:test --account-details {compteId}
```

### `o2s:link-user` — Lier un utilisateur

Lie manuellement un utilisateur à un contact O2S.

```bash
php bin/console o2s:link-user {userId} {o2sContactId}
```

---

## 2. Tâches planifiées (Cron)

### Configuration crontab

Le fichier de référence est dans `config/cron/crontab.txt`.

```crontab
# Sync incrémentale — Toutes les 15 minutes
# Détecte les NOUVEAUX contacts et synchronise leurs comptes (~10s)
*/15 * * * * cd /chemin/vers/projet && /usr/bin/php bin/console o2s:sync-incremental --env=prod --no-debug >> var/log/o2s-incremental.log 2>&1

# Mise à jour des valorisations — Toutes les 2 heures
# Met à jour les valorisations pour 50 comptes par exécution (~2-3min)
0 */2 * * * cd /chemin/vers/projet && /usr/bin/php bin/console o2s:sync-incremental --valuations -b 50 --env=prod --no-debug >> var/log/o2s-valuations.log 2>&1

# Sync complète — Tous les jours à 3h du matin
# Resynchronise TOUT (contacts + comptes + valorisations) (~15min)
0 3 * * * cd /chemin/vers/projet && /usr/bin/php bin/console o2s:sync --comptes --env=prod --no-debug >> var/log/o2s-full-sync.log 2>&1

# Nettoyage des logs — Tous les dimanches à 4h
0 4 * * 0 find /chemin/vers/projet/var/log/ -name "o2s-*.log" -mtime +7 -delete
```

### Installation sur OVH

1. Se connecter en SSH au serveur
2. Éditer le crontab : `crontab -e`
3. Adapter les chemins (`/chemin/vers/projet` → chemin réel)
4. Vérifier le chemin de PHP : `which php`

### Alternative OVH : Endpoints HTTP sécurisés

Si les cron SSH ne sont pas disponibles (mutualisé), utiliser les tâches planifiées OVH via des URLs sécurisées :

| URL | Fréquence | Description |
|-----|-----------|-------------|
| `/cron/o2s-sync-incremental?token=CRON_SECRET` | 15 min | Sync incrémentale |
| `/cron/o2s-sync-valuations?token=CRON_SECRET` | 2h | MAJ valorisations |
| `/cron/o2s-sync-full?token=CRON_SECRET` | Quotidien | Sync complète |
| `/cron/o2s-backfill-type-contacts?token=CRON_SECRET` | Ponctuel | Backfill classification Harvest |
| `/cron/o2s-fix-emails?token=CRON_SECRET` | Ponctuel | Corriger emails placeholder |
| `/cron/health?token=CRON_SECRET` | — | Health check |

> **Important** : Ces endpoints nécessitent le token `CRON_SECRET` configuré dans `.env.local`.

**Wrapper cron OVH** (`cron/o2s_cron.php`) :

```bash
php cron/o2s_cron.php incremental
php cron/o2s_cron.php full
php cron/o2s_cron.php valuations
php cron/o2s_cron.php fix-emails
php cron/o2s_cron.php backfill-type-contacts
php cron/o2s_cron.php health
```

---

## 3. Interface admin

### Accès

- **URL** : `https://votre-domaine.fr/admin/o2s`
- **Authentification** : Compte admin (`ROLE_ADMIN`)

### Actions disponibles

#### Sync rapide (bouton vert)
- Détecte les nouveaux contacts depuis O2S
- Synchronise automatiquement leurs comptes
- Durée : ~10 secondes
- À faire : régulièrement pour garder la base à jour

#### MAJ Valorisations (bouton orange)
- Met à jour les valorisations de 30 comptes par clic
- **Priorise les comptes à 0 €** (ceux qui n'ont jamais été valorisés)
- Nécessite **plusieurs clics** pour traiter tous les comptes (480 comptes ÷ 30 = ~16 lots)
- Durée par lot : ~30-60 secondes

#### Sync complète (bouton bleu)
- Étape 1 : Synchronise tous les contacts
- Étape 2 : Synchronise tous les comptes (par lots de 10 utilisateurs)
- Affiche une barre de progression
- Durée : ~5-15 minutes

### Dashboard — Lecture des statistiques

| Statistique | Description |
|-------------|-------------|
| **Encours géré** | Somme des valorisations "Gestion directe" (comparable à O2S) |
| **Épargne salariale** | PEE, PERCO |
| **Comptes agrégés** | Livrets, comptes courants, PEL (solde via `liquidity`) |
| **Total tous comptes** | Somme des 3 catégories ci-dessus |
| **Clients liés** | Nombre d'utilisateurs avec un `o2s_contact_id` |

---

## 4. Dépannage

### Les comptes agrégés affichent 0 €

**Cause** : Les comptes bancaires (Livret, CC, PEL…) utilisent le champ `liquidity` au lieu de `totalValue` dans l'API O2S.

**Solution** :
1. Vérifier que le code utilise bien la logique `totalValue || liquidity || montant` (commit `e147229`)
2. Cliquer plusieurs fois sur **"MAJ Valorisations"** pour resynchroniser les comptes à 0 €

**Vérification en base** :
```sql
-- Comptes encore à zéro
SELECT COUNT(*) FROM product_accounts
WHERE o2s_compte_id IS NOT NULL
AND (o2s_valuation IS NULL OR o2s_valuation = 0);

-- Total de la valorisation
SELECT SUM(o2s_valuation) as total FROM product_accounts
WHERE o2s_compte_id IS NOT NULL;
```

### Écart entre O2S et ADN sur "Encours géré"

L'écart est normal et peut venir de :
- **Catégorie "AUTRE"** : Certains produits non classifiés sont comptés en "Gestion directe" chez nous mais pas chez O2S
- **Timing** : Les valorisations O2S sont mises à jour en temps réel, les nôtres sont synchronisées périodiquement
- **Comptes inactifs** : O2S peut exclure certains comptes fermés que nous gardons

### Erreur d'authentification O2S

```
O2SAuthenticationException: O2S authentication failed
```

**Vérifier** :
1. Les variables d'environnement (`O2S_CLIENT_ID`, `O2S_CLIENT_SECRET`, etc.)
2. La validité des credentials chez Harvest
3. L'accessibilité réseau vers `auth.harvest.fr` et `api.office2s.com`
4. Le cache token : `rm -rf var/cache/o2s_auth/`

### EntityManager fermé pendant la sync

```
The EntityManager is closed
```

**Cause** : Une erreur SQL a provoqué la fermeture de l'EntityManager.

**Le code gère cela automatiquement** via `resetEntityManagerIfNeeded()`. Si le problème persiste, relancer la commande.

### Timeout pendant la sync complète

**Cause** : L'hébergement OVH a un timeout de 120 secondes.

**Solution** : La sync complète utilise des appels AJAX séquentiels (batches de 10 utilisateurs) pour rester sous le timeout. Si le problème persiste :
- Réduire `batch_size` à 5
- Utiliser la commande CLI plutôt que l'interface web

---

## 5. Déploiement

### Variables d'environnement requises

```env
# Credentials O2S (Harvest)
O2S_CLIENT_ID=xxx
O2S_CLIENT_SECRET=xxx
O2S_USERNAME=xxx
O2S_PASSWORD=xxx
O2S_ENVIRONMENT=production
```

### Migrations

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

La migration `Version20260203_O2SIntegration` ajoute les colonnes O2S aux tables `users_adn` et `product_accounts`.

### Première synchronisation

Après le déploiement initial :

```bash
# 1. Vérifier la connexion
php bin/console o2s:test

# 2. Synchroniser les contacts
php bin/console o2s:sync --env=prod

# 3. Synchroniser les comptes
php bin/console o2s:sync --comptes --env=prod

# 4. Fusionner les doublons (si migration depuis une base existante)
php bin/console o2s:merge-duplicates --dry-run    # Vérifier d'abord
php bin/console o2s:merge-duplicates --force       # Puis exécuter

# 5. Backfill classification Harvest (Client/Prospect)
# En CLI local :
php bin/console o2s:test --type-contacts-stats
# Sur OVH mutualisé (via HTTP) :
php cron/o2s_cron.php backfill-type-contacts

# 6. Mettre à jour les valorisations (plusieurs passages)
for i in $(seq 1 10); do
  php bin/console o2s:sync-incremental --valuations --batch-size=50
done

# 7. Installer les cron jobs
crontab -e
# Copier le contenu de config/cron/crontab.txt
```

### Checklist post-déploiement

- [ ] Variables d'environnement O2S configurées
- [ ] `php bin/console o2s:test` retourne OK
- [ ] Migration `Version20260203_O2SIntegration` exécutée
- [ ] Première sync contacts effectuée
- [ ] Première sync comptes effectuée
- [ ] Doublons vérifiés et fusionnés
- [ ] Backfill classification Harvest (Client/Prospect) exécuté
- [ ] Valorisations mises à jour
- [ ] Cron jobs installés
- [ ] Dashboard admin accessible (`/admin/o2s`)

---

## 6. Monitoring

### Logs

| Fichier | Contenu |
|---------|---------|
| `var/log/o2s-incremental.log` | Sync incrémentale (cron 15min) |
| `var/log/o2s-valuations.log` | Mises à jour valorisations (cron 2h) |
| `var/log/o2s-full-sync.log` | Sync complète quotidienne |
| `var/log/dev.log` ou `prod.log` | Logs Symfony détaillés |

### Requêtes SQL utiles

```sql
-- Nombre de clients liés à O2S
SELECT COUNT(*) FROM users_adn WHERE o2s_contact_id IS NOT NULL;

-- Nombre de comptes O2S
SELECT COUNT(*) FROM product_accounts WHERE o2s_compte_id IS NOT NULL;

-- Valorisation totale
SELECT SUM(o2s_valuation) FROM product_accounts WHERE o2s_compte_id IS NOT NULL;

-- Répartition par catégorie
SELECT product_type, COUNT(*) as nb, SUM(o2s_valuation) as total
FROM product_accounts
WHERE o2s_compte_id IS NOT NULL
GROUP BY product_type
ORDER BY total DESC;

-- Comptes à valorisation 0
SELECT pa.id, pa.display_alias, pa.product_type, u.first_name, u.last_name
FROM product_accounts pa
JOIN users_adn u ON pa.user_id = u.id
WHERE pa.o2s_compte_id IS NOT NULL
AND (pa.o2s_valuation IS NULL OR pa.o2s_valuation = 0)
ORDER BY pa.product_type;

-- Dernière synchronisation
SELECT MIN(o2s_synced_at) as oldest, MAX(o2s_synced_at) as newest
FROM product_accounts WHERE o2s_compte_id IS NOT NULL;

-- Doublons potentiels
SELECT COUNT(*) FROM users_adn a
JOIN users_adn b ON (
  LOWER(REPLACE(REPLACE(TRIM(a.last_name), ' ', ''), '-', ''))
  = LOWER(REPLACE(REPLACE(TRIM(b.last_name), ' ', ''), '-', ''))
  AND LOWER(REPLACE(REPLACE(TRIM(a.first_name), ' ', ''), '-', ''))
  = LOWER(REPLACE(REPLACE(TRIM(b.first_name), ' ', ''), '-', ''))
)
WHERE a.o2s_contact_id IS NULL AND b.o2s_contact_id IS NOT NULL
AND a.id != b.id AND b.email LIKE 'o2s_%@placeholder.local';
```

### Alertes recommandées

| Indicateur | Seuil d'alerte | Vérification |
|------------|----------------|--------------|
| Comptes à 0 € | > 50 | Cron valorisations bloqué ? |
| Sync la plus ancienne | > 48h | Cron cassé ? |
| Nombre de doublons | > 0 | Lancer fusion |
| Erreurs dans les logs | > 10/jour | API O2S down ? |



