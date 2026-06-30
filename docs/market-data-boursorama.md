# MarketData — Pipeline VL Boursorama + Conversion FX BCE

> **Statut** : ✅ En production (phase 1 : affichage live)
> **Dernière maj** : 28 mai 2026
> **Documents liés** : [o2s-valuations-live.md](./o2s-valuations-live.md), [o2s-integration.md](./o2s-integration.md)

---

## 1. Pourquoi ?

L'API Harvest/O2S renvoie pour chaque support détenu un **snapshot figé** : la dernière valeur liquidative (VL) calculée par l'assureur lors du dernier batch. Ce snapshot peut être daté de plusieurs jours, voire semaines, alors que la valeur réelle du fonds a bougé entre-temps.

Conséquence pour le client : son détail produit ADN affiche des montants qui ne correspondent pas à ce qu'il voit sur l'extranet de l'assureur, lequel a souvent une vue plus fraîche.

**Solution mise en place** : à l'affichage, on **conserve les quantités et la composition** issues de l'API O2S (sources de vérité), mais on **recalcule chaque ligne** avec une VL fraîche scrapée publiquement sur Boursorama, convertie en EUR via les taux officiels BCE pour les fonds en devises étrangères.

---

## 2. Architecture

```
┌────────────────────┐     ┌──────────────────────┐
│  AccountDetails    │     │  LiveQuoteService    │
│  (O2S API)         │────▶│  (orchestrateur)     │
│  quantités + ISIN  │     └──────┬───────────────┘
└────────────────────┘            │
                                  ▼
                  ┌───────────────────────────┐
                  │  MarketQuoteResolver       │
                  │  cascade plug-and-play     │
                  └──┬─────────────────────┬──┘
                     │ 1. priorité         │ 2. fallback (rétrocompat)
                     ▼                     ▼
        ┌────────────────────────┐  ┌──────────────────┐
        │ BoursoramaQuoteProvider│  │ QuoteAggregator  │
        │  + FxConverter (BCE)   │  │ (TwelveData/Yahoo)│
        └─────────┬──────────────┘  └──────────────────┘
                  │
                  ▼
        ┌─────────────────────┐
        │ ResolvedQuote       │
        │ - nav EUR           │
        │ - nativeNav + ccy   │
        │ - fxRate            │
        │ - source            │
        └─────────────────────┘
```

### Composants

| Classe | Rôle |
|---|---|
| `MarketQuoteProviderInterface` | Contrat pluggable pour toute source de VL (Bourso, Quantalys, FE fundinfo demain…). Auto-taggué `app.market_quote_provider`. |
| `Quote` | DTO immuable : ISIN, nav, currency, navDate, source. |
| `BoursoramaQuoteProvider` | Scraping public `https://www.boursorama.com/cours/{ISIN}/`. Cache Symfony 6 h. Garde-fou contre faux positifs (vérifie `/cours/` dans l'URL finale). |
| `EurListingMapping` | Override `ISIN → symbole Boursorama` pour les ETF multi-cotés (forcer la place EUR plutôt que LSE USD). Fichier : `config/market_data/eur_listings.php`. |
| `FxRateProviderInterface` + `EcbFxRateProvider` | Taux de change officiels Banque Centrale Européenne. Source : `eurofxref-daily.xml`. Gratuit, sans authentification, mis à jour vers 16h CET J ouvré. Cache 24 h. |
| `FxConverter` | Conversion `USD/GBP/CHF/JPY/… ⇄ EUR` via les taux BCE. |
| `MarketQuoteResolver` | Orchestre les providers en cascade + applique FX si la devise diffère de la cible. Retourne un `ResolvedQuote`. |
| `LiveQuoteService` | Service de plus haut niveau utilisé par les Controllers : essaie le résolveur Boursorama, puis retombe sur l'ancien `QuoteAggregator` (TwelveData/Yahoo) pour les fonds non couverts par Bourso. |

---

## 3. Pipeline détaillé

### Cas 1 — Fonds EUR direct (95 % des cas)

```text
LiveQuoteService.getLiveNavEur('FR0010149302')
  → MarketQuoteResolver.resolveEur(...)
    → BoursoramaQuoteProvider.getQuote(...)
      → fetch https://www.boursorama.com/cours/FR0010149302/
      → parse <span data-ist-last>1 891,26</span> + <span>EUR</span>
      → Quote(nav=1891.26, currency=EUR, date=2026-05-26)
    → devise déjà EUR, pas de conversion
    → ResolvedQuote(quote=…, nativeQuote=…, fxRate=null)
```

### Cas 2 — Fonds USD natif (ex: CPR Invest Global Gold Mines `LU1989766289`)

```text
LiveQuoteService.getLiveNavEur('LU1989766289')
  → MarketQuoteResolver.resolveEur(...)
    → BoursoramaQuoteProvider.getQuote(...)
      → fetch /cours/LU1989766289/
      → Quote(nav=225.80, currency=USD, date=2026-05-26)
    → devise ≠ EUR → FxConverter
      → ECB: 1 EUR = 1.1637 USD
      → 225.80 / 1.1637 = 194.04 EUR
    → ResolvedQuote(
        quote=Quote(nav=194.04, currency=EUR, source='boursorama+fx'),
        nativeQuote=Quote(nav=225.80, currency=USD),
        fxRate=0.8594,
      )
```

### Cas 3 — ETF multi-coté (ex: iShares Core S&P 500 `IE00B5BMR087`)

Boursorama renvoie par défaut la cotation LSE (USD). Deux options :

- **Option A (préférée)** : ajouter un override dans `config/market_data/eur_listings.php` avec le symbole Boursorama de la place Xetra/Borsa Italiana (EUR direct, pas de FX).
- **Option B (par défaut)** : le résolveur convertit USD → EUR via BCE (acceptable, écart minime).

### Cas 4 — Fonds non couvert par Boursorama (rare)

```text
LiveQuoteService.getLiveNavEur('LU9999999991')
  → MarketQuoteResolver.resolveEur(...) → null
  → fallback QuoteAggregator.getLast(...) → TwelveData/Yahoo
  → si toujours null → on garde le snapshot O2S (comportement actuel)
```

---

## 4. Conversion FX — détails BCE

| Item | Valeur |
|---|---|
| Source | https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml |
| Authentification | Aucune |
| Fréquence | Quotidienne, J ouvré vers 16 h CET |
| Devises couvertes | 31 majeures (USD, GBP, CHF, JPY, CAD, AUD, CNY, etc.) |
| Cache local | 24 h (Symfony `cache.app`) |
| Coût | Gratuit (organisme public) |
| Méthode de calcul | `value_eur = value_native / rate_native_per_eur` |

> **Limite connue** : la BCE publie un taux unique de référence du jour, alors que l'assureur (Generali, CNP…) utilise potentiellement un taux interne légèrement différent. Écart résiduel attendu : **< 0,5 %**, négligeable vs. les écarts de plusieurs % qu'on cherche à corriger.

---

## 5. Intégration dans le code applicatif

### Affichage détail produit (`DashboardController::renderO2SProduct`)

Le bloc `liveNavByIsin` consomme désormais `LiveQuoteService::getLiveNavEur()` au lieu de `QuoteAggregator::getLast()`. Chaque ligne du tableau `$table[]` exposé au template contient trois nouveaux champs :

```php
'priceSource'              => 'boursorama' | 'boursorama+fx' | 'twelvedata|yahoo' | null,
'priceSourceNativeCurrency'=> 'USD' | 'GBP' | … | null,
'priceSourceFxRate'        => 0.8594 | … | null,
```

Le template peut afficher un badge type **« VL Boursorama du 26/05 »** ou **« VL Boursorama + FX BCE (USD→EUR) »** pour la transparence client.

### CLI de comparaison & audit

```bash
# Compare ligne à ligne O2S vs (qtés O2S × VL Bourso + FX) pour un compte
php bin/console app:vl-compare COC000056

# JSON pour intégration pipeline
php bin/console app:vl-compare OC830110468 --json
```

### CLI de préchauffage cache (à brancher sur cron OVH)

```bash
# Tous les comptes
php bin/console app:vl-warm-cache

# Pagination pour OVH mutualisé (timeout HTTP de 60s)
php bin/console app:vl-warm-cache --offset=0   --limit=100
php bin/console app:vl-warm-cache --offset=100 --limit=100
php bin/console app:vl-warm-cache --offset=200 --limit=100

# Test sans appel réel
php bin/console app:vl-warm-cache --dry-run --limit=10
```

---

## 6. Mapping ETF multi-cotés

Fichier : `config/market_data/eur_listings.php`

Format :

```php
return [
    'IE00B5BMR087' => [
        'boursoramaSymbol'  => '1rPCSPX',
        'preferredCurrency' => 'EUR',
        'note'              => 'iShares Core S&P 500 — Xetra EUR (au lieu LSE USD)',
    ],
    // …
];
```

**Comment trouver le symbole Boursorama ?**
1. https://www.boursorama.com/cours/{ISIN}/
2. Section « Autres places de cotation »
3. Cliquer sur la ligne EUR → l'URL devient `/cours/{symbole}/` — c'est ce symbole à reporter.

**Quand ajouter un override ?**
- Si la précision FX BCE ne suffit pas (typiquement pour les ETF à forte rotation et gros volumes EUR).
- Si l'assureur utilise une cotation EUR spécifique (Generali souvent → Borsa Italiana, CNP → Xetra).

**Quand NE PAS ajouter d'override ?**
- Si le fonds n'a pas de classe EUR (cas typique : fonds Gold Mines, Emerging USD Bond) — le résolveur convertit via FX, c'est la bonne approche.

---

## 7. Cache et performance

| Étage | TTL | Storage | Notes |
|---|---|---|---|
| Boursorama VL | 6 h | `cache.app` (filesystem en dev, Redis en prod) | Les VL sont publiées 1×/jour, 6 h évite tout sur-fetch |
| Échec Bourso | 15 min | idem | Pour retenter rapidement après un blip réseau |
| Taux ECB | 24 h | `cache.app` | Publication quotidienne BCE |
| Échec ECB | 5 min | idem | |

Performance attendue (cache chaud) :
- `LiveQuoteService.getLiveNavEur(isin)` : < 5 ms
- Détail produit avec 8 supports : < 50 ms ajoutés par rapport au sans-Bourso

Performance cache froid :
- Première visite produit : ~800 ms par ISIN (HTTP Bourso) + 200 ms BCE one-shot
- D'où l'intérêt du `app:vl-warm-cache` quotidien.

---

## 8. Sécurité & ToS

- Boursorama ne propose pas d'API publique mais la fiche cours est publiquement consultable. **Le scraping respectueux** (User-Agent identifié, cache 6 h, 1 requête/ISIN/jour ouvré, throttle 500 ms) est usuellement toléré.
- Audit ToS recommandé en parallèle (1 h juriste). Si refus formel : pivot prévu vers `Quantalys API Data` (existe déjà, payant ~1 500€/an).
- La BCE est un organisme public, redistribution autorisée et encouragée.

---

## 9. Roadmap

### Phase 1 — Affichage live (FAIT ✅)
- [x] `MarketQuoteProviderInterface` + Quote DTO
- [x] `BoursoramaQuoteProvider` avec mapping multi-cotés
- [x] `EcbFxRateProvider` + `FxConverter`
- [x] `MarketQuoteResolver` + `LiveQuoteService`
- [x] Intégration `DashboardController::renderO2SProduct`
- [x] Commande `app:vl-compare` (audit) + `app:vl-warm-cache` (cron)
- [x] Tests unitaires (20 tests / 59 assertions)

### Phase 2 — Persistance & monitoring
- [ ] Migration BDD : colonnes `o2s_valuation_recalculated`, `o2s_valuation_source` sur `product_accounts`
- [ ] Intégrer le résolveur dans `O2SSyncService::syncValuationsBatch` (cron quotidien)
- [ ] Dashboard admin O2S : taux de couverture Boursorama, % conversions FX, alertes
- [ ] Badge UI « VL Boursorama du jj/mm » dans `templates/dashboard/produit_show.html.twig`

### Phase 3 — Sources additionnelles
- [ ] `QuantalysApiQuoteProvider` (si budget validé, ~1 500€/an)
- [ ] Mapping des ETF multi-cotés (objectif : ~50 entrées dans `eur_listings.php`)

---

## 10. Annexes

### Exécuter le POC sans bootstrap Symfony

```bash
php tests/Poc/test-resolver.php                  # batch d'ISIN par défaut
php tests/Poc/test-resolver.php LU1989766289     # un seul ISIN
```

Sortie attendue (taux ECB de référence, exemple du 27/05/2026) :

```
Taux BCE chargés (date publication : 2026-05-27)
  1 EUR = 1.1637 USD | 0.8662 GBP | 0.9153 CHF | 185.5200 JPY

  ISIN             VL native  Ccy   VL → EUR    Date       Status
  FR0010149302    1 891.2600  EUR   1 891.2600  26/05/2026 ✓ EUR direct
  LU1989766289      225.8000  USD     194.0363  26/05/2026 ⇄ USD→EUR (taux 0.859328)
```

### Lancer les tests unitaires de la couche MarketData

```bash
php vendor/bin/phpunit tests/Services/MarketData/
```
