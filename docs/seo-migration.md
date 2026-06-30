# Migration SEO - ADN Family Office

## Contexte

Migration du site WordPress (`adnfamilyoffice.fr`) vers le nouveau site Symfony.

**Objectif** : Préserver le référencement des articles de blog existants.

## Structure des URLs

### Différences de structure

| Élément | WordPress (ancien) | Symfony (nouveau) |
|---------|-------------------|-------------------|
| Page Actualités | `/articles/` | `/actualites` |
| Article individuel | `/YYYY/MM/DD/slug/` | `/actualites/article/slug` |

### Exemple concret

- **Ancien** : `https://adnfamilyoffice.fr/2025/10/29/allier-diversification-et-avantage-fiscal/`
- **Nouveau** : `https://adnfamilyoffice.fr/actualites/article/allier-diversification-et-avantage-fiscal`

## Mapping des URLs (Articles à migrer)

### Articles récupérés depuis https://adnfamilyoffice.fr/articles/

| # | Titre | Date | Ancienne URL (WordPress) | Nouvelle URL (Symfony) | Slug |
|---|-------|------|--------------------------|------------------------|------|
| 1 | Allier diversification et avantage fiscal | 29/10/2025 | `/2025/10/29/allier-diversification-et-avantage-fiscal/` | `/actualites/article/allier-diversification-et-avantage-fiscal` | `allier-diversification-et-avantage-fiscal` |
| 2 | L'épargne de précaution : le socle d'une stratégie patrimoniale équilibrée | 22/10/2025 | `/2025/10/22/lepargne-de-precaution-le-socle-dune-strategie-patrimoniale-equilibree/` | `/actualites/article/lepargne-de-precaution-le-socle-dune-strategie-patrimoniale-equilibree` | `lepargne-de-precaution-le-socle-dune-strategie-patrimoniale-equilibree` |
| 3 | Pourquoi le contexte actuel donne tout son intérêt à la « pierre-papier » ? | 01/10/2025 | `/2025/10/01/pourquoi-le-contexte-actuel-donne-tout-son-interet-a-la-pierre-papier/` | `/actualites/article/pourquoi-le-contexte-actuel-donne-tout-son-interet-a-la-pierre-papier` | `pourquoi-le-contexte-actuel-donne-tout-son-interet-a-la-pierre-papier` |
| 4 | Comment tirer profit de l'intelligence artificielle ? | 19/02/2025 | `/2025/02/19/comment-tirer-profit-de-lintelligence-artificielle/` | `/actualites/article/comment-tirer-profit-de-lintelligence-artificielle` | `comment-tirer-profit-de-lintelligence-artificielle` |
| 5 | Assurance-Vie: Quand et Pourquoi Changer de Contrat ? | 30/01/2025 | `/2025/01/30/assurance-vie-quand-et-pourquoi-changer-de-contrat/` | `/actualites/article/assurance-vie-quand-et-pourquoi-changer-de-contrat` | `assurance-vie-quand-et-pourquoi-changer-de-contrat` |
| 6 | Impact des Élections Américaines sur les Marchés Financiers | 28/10/2024 | `/2024/10/28/impact-des-elections-americaines-sur-les-marches-financiers/` | `/actualites/article/impact-des-elections-americaines-sur-les-marches-financiers` | `impact-des-elections-americaines-sur-les-marches-financiers` |

> **Note** : La page WordPress indique "Page suivante", il pourrait y avoir d'autres articles.

## Stratégie de redirection

### Redirections 301

Les redirections 301 (permanentes) sont la meilleure pratique SEO pour :
- Transférer le "jus SEO" (PageRank) des anciennes URLs vers les nouvelles
- Éviter les erreurs 404 pour les utilisateurs avec d'anciens liens/favoris
- Informer Google que le contenu a déménagé définitivement

### Implémentation

Les redirections sont gérées dans :
- `src/Controller/LegacyRedirectController.php` - Contrôleur dédié aux redirections
- Pattern regex pour capturer les anciennes URLs WordPress

### Pattern de redirection

```
/YYYY/MM/DD/slug/ → /actualites/article/slug
```

## Checklist de migration

- [ ] Lister tous les articles WordPress existants
- [ ] Créer les articles correspondants dans Symfony avec les mêmes slugs
- [ ] Implémenter les redirections 301
- [ ] Tester chaque redirection
- [ ] Soumettre le nouveau sitemap à Google Search Console
- [ ] Surveiller les erreurs 404 dans Search Console pendant 3 mois

## Actions post-migration

1. **Google Search Console** :
   - Soumettre le nouveau sitemap (`/sitemap.xml`)
   - Utiliser l'outil "Changement d'adresse" si le domaine change
   - Surveiller les erreurs d'exploration

2. **Robots.txt** :
   - S'assurer que les nouvelles URLs sont accessibles
   - Ne pas bloquer les anciennes URLs (pour que les redirections fonctionnent)

3. **Monitoring** :
   - Vérifier le trafic organique pendant 2-3 mois
   - Surveiller le positionnement des mots-clés principaux

## Nouvelles pages (qui n'existaient pas sur WordPress)

### Pas besoin de redirection !

Les nouvelles pages du site Symfony qui n'existaient pas sur WordPress **n'ont pas besoin de redirection** car :
- Elles n'ont pas d'historique SEO à préserver
- Elles n'ont pas de backlinks existants
- Google va les découvrir comme du contenu nouveau

### Actions SEO pour les nouvelles pages

1. **S'assurer que les balises meta sont correctes** :
   - Titre unique et descriptif (50-60 caractères)
   - Meta description engageante (150-160 caractères)
   - URL propre et descriptive

2. **Soumettre le sitemap** :
   - Le sitemap XML sera automatiquement généré
   - Le soumettre à Google Search Console

3. **Créer des liens internes** :
   - Lier les nouvelles pages depuis les pages existantes
   - Créer un maillage interne cohérent

4. **Contenu de qualité** :
   - Contenu unique et original
   - Images optimisées avec attributs alt
   - Structure avec H1, H2, H3

### Liste des nouvelles pages principales

| Page | URL | Priorité SEO |
|------|-----|--------------|
| Services - Gestion de patrimoine | `/services/gestion-patrimoine` | Haute |
| Services - Gestion immobilière | `/services/gestion-immobiliere` | Haute |
| Services - Transmission | `/services/transmission-succession` | Haute |
| Expertise | `/expertise` | Haute |
| Espace client | `/user/dashboard` | Basse (privé) |
| KYC | `/register/kyc/*` | Basse (privé) |

## Notes techniques

- Les redirections 301 transmettent ~90-99% du PageRank
- Google peut mettre 2-4 semaines pour mettre à jour son index
- Conserver les redirections pendant au moins 1 an
- Les nouvelles pages seront indexées progressivement

