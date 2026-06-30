# Crons OVH — Installation

Scripts cron PHP **autonomes** (pas de wrapper HTTP) destinés à être exécutés
par l'infrastructure CRON d'OVH mutualisé via l'**Espace client OVH**.

## Pourquoi ces scripts ?

OVH mutualisé interdit les self-loops HTTP : un script PHP exécuté en SSH/CLI
ne peut pas appeler son propre domaine. Le wrapper `cron/o2s_cron.php` (qui
fait du `file_get_contents()` HTTP) ne fonctionne donc pas.

À la place, ces scripts **bootstrappent Symfony directement** et appellent les
services en interne. L'infrastructure CRON d'OVH a un contexte réseau distinct
de SSH et autorise les appels sortants vers `auth.harvest.fr` et `api.office2s.com`.

## Convention OVH respectée

| Élément | Valeur |
|---|---|
| Path | `www/cron/ovh/<script>.php` |
| Extension | `.php` |
| PHP version | 8.2 (à sélectionner dans le manager) |
| Permissions | 644 |
| Chemins internes | absolus (via `__DIR__`) |
| Durée max | < 60 min (limite OVH) |
| Logs | `var/log/o2s-cron.log` + sortie stdout |

> ⚠️ **Limite OVH** : 1 exécution par heure maximum, pas de paramètres URL.

## Liste des scripts

| Fichier | Action | Fréquence recommandée | Crontab (mode expert) |
|---|---|---|---|
| `o2s_incremental.php` | Sync nouveaux contacts + comptes manquants | toutes les heures | `? * * * *` (laissé à OVH) |
| `o2s_valuations.php` | MAJ des valorisations (lot de 50) | toutes les heures | `? * * * *` |
| `o2s_sync_all_comptes.php` | Détection nouveaux contrats par user (paginé) | toutes les 2 heures | `? */2 * * *` |
| `o2s_full.php` | Sync complète quotidienne | 1×/jour à 4h | `? 4 * * *` |
| `o2s_fix_emails.php` | Récupère les vrais emails Harvest (placeholders) | 1×/jour à 5h | `? 5 * * *` |
| `o2s_warm_cache.php` | Préchauffe le cache (versements / historiques / patrimoine) | 1×/jour à 6h | `? 6 * * *` |
| `vl_warm_cache.php` | Préchauffe le cache des VL Boursorama (cours fonds/ETF) | 1×/jour à 14h | `? 14 * * *` |

## Installation dans l'Espace client OVH

1. Connexion : <https://www.ovh.com/manager/web/>
2. Sélectionne ton hébergement web
3. Onglet **Plus** → **Cron**
4. **Ajouter une planification**, pour chacun des 4 scripts :

### Script #1 — Sync incrémentale
| Champ | Valeur |
|---|---|
| Commande à exécuter | `www/cron/ovh/o2s_incremental.php` |
| Langage | `PHP 8.2` |
| Activation | activée |
| Logs par e-mail | ton adresse admin (recommandé) |
| Description | `O2S - Sync incrémentale (nouveaux contacts)` |
| Périodicité | mode simple : **chaque heure** |

### Script #2 — Valorisations
| Champ | Valeur |
|---|---|
| Commande | `www/cron/ovh/o2s_valuations.php` |
| Description | `O2S - MAJ valorisations (lot de 50)` |
| Périodicité | **chaque heure** |

### Script #3 — Détection nouveaux contrats
| Champ | Valeur |
|---|---|
| Commande | `www/cron/ovh/o2s_sync_all_comptes.php` |
| Description | `O2S - Détection nouveaux contrats (paginé)` |
| Périodicité | mode expert : `? */2 * * *` (toutes les 2 heures) |

### Script #4 — Sync complète quotidienne
| Champ | Valeur |
|---|---|
| Commande | `www/cron/ovh/o2s_full.php` |
| Description | `O2S - Sync complète quotidienne` |
| Périodicité | mode simple : **tous les jours à 4h** |

### Script #5 — Correction des emails placeholder
| Champ | Valeur |
|---|---|
| Commande | `www/cron/ovh/o2s_fix_emails.php` |
| Description | `O2S - Correction emails placeholder (MoneyPitch)` |
| Périodicité | mode expert : `? 5 * * *` (tous les jours à 5h, après o2s_full) |

> 💡 Indispensable pour la redirection MoneyPitch : MoneyPitch authentifie
> les clients par leur vraie adresse email. Tant qu'un user a un email
> `xxx@placeholder.local`, sa redirection MoneyPitch échouera.

### Script #6 — Préchauffage du cache O2S
| Champ | Valeur |
|---|---|
| Commande | `www/cron/ovh/o2s_warm_cache.php` |
| Description | `O2S - Préchauffage cache (dashboard rapide)` |
| Périodicité | mode expert : `? 6 * * *` (tous les jours à 6h, après o2s_fix_emails) |

> ⚡ Performance : préchauffe à 6h le cache des versements, historiques 6 mois
> et patrimoine global pour TOUS les users O2S. Pendant les 24h qui suivent,
> l'ouverture du dashboard répond en quelques dizaines de ms au lieu de 30-120 s.
> Durée typique du cron : 5-30 min selon le nombre de contrats.

### Script #7 — Préchauffage des VL Boursorama
| Champ | Valeur |
|---|---|
| Commande | `www/cron/ovh/vl_warm_cache.php` |
| Description | `MarketData - Préchauffage VL Boursorama (détail produit rapide)` |
| Périodicité | mode expert : `? 14 * * *` (tous les jours à 14h) |

> 💹 Boursorama publie les VL J+1 ouvré entre 11h et 13h CET. Tourner à 14h
> garantit qu'on récupère les dernières publications. Préchauffe les VL pour
> tous les ISIN actifs (~200-500 supports). Pendant les 6h qui suivent, les
> visites détail produit dans le dashboard servent les VL fraîches en ~5 ms.
> Conversion FX BCE automatique pour les fonds USD/GBP/CHF/JPY/…
>
> Doc complète : `docs/market-data-boursorama.md`

## Test manuel (avant d'activer les crons)

> ⚠️ Le test SSH **ne fonctionne pas** car OVH bloque les sorties Harvest en SSH.
> La validation se fait uniquement via le déclenchement OVH cron lui-même.

Pour tester un script via le manager OVH :
1. Crée la tâche avec activation
2. Attends qu'elle s'exécute (ou édite-la pour la déclencher rapidement)
3. Vérifie les logs :
   - **OVH manager** : Logs > Catégorie "CRON"
   - **Application** : `~/www/var/log/o2s-cron.log`

## Format des logs applicatifs

```
[2026-04-30 14:00:01] [incremental] [INFO] START
[2026-04-30 14:00:42] [incremental] [OK] Terminé en 41.2s
```

En cas d'erreur :
```
[2026-04-30 14:05:01] [valuations] [ERROR] Exit code 1 après 12.5s
[2026-04-30 14:05:01] [valuations] [FATAL] Connection refused {"file":"...","line":42}
```

## Désactivation

Si tu veux désactiver temporairement un cron, va dans le manager OVH > Cron >
clique sur `...` à côté de la tâche > **Modifier** > décoche "Activation".

Le système OVH **désactive automatiquement** une tâche après **10 échecs
consécutifs**. Tu reçois alors un mail.
