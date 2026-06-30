<?php

declare(strict_types=1);

namespace App\Integration\O2S\Service;

use App\Entity\ManualPamOverride;
use App\Entity\ProductAccount;
use App\Integration\O2S\Client\O2SClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Computes the Average Purchase Price (PAM = Prix Moyen d'Achat) from
 * historical API snapshots for accounts where the API doesn't provide it.
 *
 * Algorithm:
 * 1. Fetch ~6 months of weekly snapshots from /accounts/{id}/account-details
 * 2. Sort chronologically (oldest → newest)
 * 3. For positions with quantity changes: weighted-average PAM from observed purchases
 * 4. For static positions (no qty changes): use oldest NAV as initial PAM, then
 *    apply dampened backward extrapolation if the fund grew and the account
 *    was opened before our observation window (uses dateOuverture from /comptes/{id})
 * 5. Dampening factor = min(1, observedDays / unobservedDays) to limit speculation
 */
final class PamCalculationService
{
    public function __construct(
        private readonly O2SClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $o2sCache,
        private readonly \App\Integration\O2S\Service\CompteService $compteService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, array{pam: float, qty: float, source: string}> Keyed by assetId
     *         source = 'manual' | 'computed'
     */
    public function computePamForAccount(string $accountId): array
    {
        $manualOverrides = $this->getManualPamOverrides($accountId);

        $cacheKey = 'o2s_computed_pam_' . md5($accountId);
        $computed = $this->o2sCache->get($cacheKey, function (ItemInterface $item) use ($accountId) {
            $item->expiresAfter(86400);
            return $this->doCompute($accountId);
        });

        $result = [];

        foreach ($computed as $assetId => $data) {
            if (isset($manualOverrides[$assetId])) {
                $result[$assetId] = [
                    'pam' => (float) $manualOverrides[$assetId],
                    'qty' => $data['qty'],
                    'source' => 'manual',
                ];
            } else {
                $result[$assetId] = [
                    'pam' => $data['pam'],
                    'qty' => $data['qty'],
                    'source' => 'computed',
                ];
            }
        }

        foreach ($manualOverrides as $assetId => $pamValue) {
            if (!isset($result[$assetId])) {
                $result[$assetId] = [
                    'pam' => (float) $pamValue,
                    'qty' => 0.0,
                    'source' => 'manual',
                ];
            }
        }

        return $result;
    }

    /**
     * Compute raw PAM without cache or manual overrides (for batch fill).
     * @return array<string, array{pam: float, qty: float}>
     */
    public function computeRawPam(string $accountId): array
    {
        return $this->doCompute($accountId);
    }

    /**
     * @return array<string, string> assetId => pamValue
     */
    private function getManualPamOverrides(string $accountId): array
    {
        $product = $this->entityManager->getRepository(ProductAccount::class)
            ->findOneBy(['o2sCompteId' => $accountId]);

        if (!$product) {
            return [];
        }

        $overrides = $this->entityManager->getRepository(ManualPamOverride::class)
            ->findBy(['productAccount' => $product]);

        $result = [];
        foreach ($overrides as $override) {
            $result[$override->getAssetId()] = $override->getPamValue();
        }

        return $result;
    }

    /**
     * @return array<string, array{pam: float, qty: float}>
     */
    private function doCompute(string $accountId): array
    {
        $snapshots = $this->fetchFullHistory($accountId);

        if (empty($snapshots)) {
            $this->logger->debug('PAM: no history for account', ['accountId' => $accountId]);
            return [];
        }

        // Sort chronologically (oldest first)
        usort($snapshots, fn(array $a, array $b) => strcmp($a['date'], $b['date']));

        // Try to get account opening date for backward extrapolation
        $dateOuverture = $this->getAccountOpeningDate($accountId);

        // Build per-asset timeline: assetId → [{date, qty, nav, value}, ...]
        $assetTimelines = [];
        foreach ($snapshots as $snap) {
            foreach ($snap['situation'] as $line) {
                $aid = (string) $line['assetId'];
                $assetTimelines[$aid][] = [
                    'date' => $snap['date'],
                    'qty'  => (float) ($line['quantity'] ?? 0),
                    'nav'  => (float) ($line['netAssetValue'] ?? 0),
                    'value' => (float) ($line['value'] ?? 0),
                ];
            }
        }

        $result = [];

        foreach ($assetTimelines as $assetId => $timeline) {
            $pam = $this->computeWeightedPam($timeline, $dateOuverture);
            if ($pam !== null) {
                $lastEntry = end($timeline);
                $result[$assetId] = [
                    'pam' => $pam,
                    'qty' => $lastEntry['qty'],
                    'computed' => true,
                ];
            }
        }

        $this->logger->info('PAM computed from history', [
            'accountId' => $accountId,
            'assetsWithPam' => count($result),
            'snapshotCount' => count($snapshots),
        ]);

        return $result;
    }

    private function computeWeightedPam(array $timeline, ?\DateTimeImmutable $dateOuverture = null): ?float
    {
        if (empty($timeline)) {
            return null;
        }

        $first = $timeline[0];
        $last = end($timeline);

        // Skip fonds euros (qty=0 throughout)
        if ($first['qty'] == 0 && $first['nav'] == 0) {
            return null;
        }

        // Initialize: assume all initial shares were bought at the oldest NAV
        $currentQty = $first['qty'];
        $currentPam = $first['nav'];

        if ($currentQty <= 0 || $currentPam <= 0) {
            return null;
        }

        $totalInvested = $currentPam * $currentQty;
        $hasQtyChanges = false;

        for ($i = 1, $count = count($timeline); $i < $count; $i++) {
            $prev = $timeline[$i - 1];
            $curr = $timeline[$i];

            $deltaQty = $curr['qty'] - $prev['qty'];

            if ($deltaQty > 0.0001) {
                $hasQtyChanges = true;
                $purchaseNav = $curr['nav'];
                if ($purchaseNav <= 0) {
                    continue;
                }

                $totalInvested += $purchaseNav * $deltaQty;
                $currentQty = $curr['qty'];
                $currentPam = $totalInvested / $currentQty;

                $this->logger->debug('PAM: purchase detected', [
                    'date' => $curr['date'],
                    'deltaQty' => round($deltaQty, 4),
                    'nav' => $purchaseNav,
                    'newPam' => round($currentPam, 4),
                ]);
            } elseif ($deltaQty < -0.0001) {
                $hasQtyChanges = true;
                $currentQty = $curr['qty'];
                if ($currentQty > 0) {
                    $totalInvested = $currentPam * $currentQty;
                } else {
                    $totalInvested = 0;
                }
            }
        }

        if ($currentQty <= 0) {
            return null;
        }

        // For static positions with growth, apply dampened backward extrapolation
        if (!$hasQtyChanges && $dateOuverture !== null && count($timeline) >= 20) {
            $extrapolated = $this->applyDampenedExtrapolation($timeline, $dateOuverture, $currentPam);
            if ($extrapolated !== null) {
                $currentPam = $extrapolated;
            }
        }

        return round($currentPam, 4);
    }

    /**
     * For static positions (no quantity changes), estimates the true purchase price
     * by projecting the observed NAV growth trend backward to the account opening date.
     *
     * Only applies when the fund GREW during the observation period (positive return),
     * because for declining funds the oldest NAV is already a good upper bound for PAM.
     *
     * Uses a dampening factor = min(1, observedDays/unobservedDays) so the projection
     * becomes increasingly conservative as the unobserved gap grows.
     */
    private function applyDampenedExtrapolation(
        array $timeline,
        \DateTimeImmutable $dateOuverture,
        float $currentPam,
    ): ?float {
        $first = $timeline[0];
        $last = end($timeline);

        $firstDate = new \DateTimeImmutable($first['date']);
        $lastDate = new \DateTimeImmutable($last['date']);

        // Only extrapolate if account opened BEFORE our first snapshot
        if ($dateOuverture >= $firstDate) {
            return null;
        }

        $observedDays = max(1, (int) $firstDate->diff($lastDate)->days);
        $unobservedDays = (int) $dateOuverture->diff($firstDate)->days;

        if ($unobservedDays < 30) {
            return null; // Gap too small, current approach is fine
        }

        // Calculate daily return from observed period
        $navRatio = $last['nav'] / $first['nav'];
        $dailyReturn = pow($navRatio, 1.0 / $observedDays) - 1.0;

        // Only extrapolate for growing funds (positive daily return)
        if ($dailyReturn <= 0) {
            return null;
        }

        // Dampening: more conservative as the unobserved period grows
        $dampening = min(1.0, $observedDays / $unobservedDays);
        $dampenedDailyReturn = $dailyReturn * $dampening;

        // Project backward: at account opening, NAV was presumably lower
        $growthFactor = pow(1.0 + $dampenedDailyReturn, $unobservedDays);
        $extrapolatedPam = $first['nav'] / $growthFactor;

        // Sanity check: PAM shouldn't be negative or unreasonably low
        if ($extrapolatedPam <= 0 || $extrapolatedPam < $first['nav'] * 0.3) {
            $this->logger->debug('PAM: extrapolation rejected (unreasonable value)', [
                'extrapolated' => $extrapolatedPam,
                'firstNav' => $first['nav'],
            ]);
            return null;
        }

        $this->logger->debug('PAM: dampened extrapolation applied', [
            'firstNav' => round($first['nav'], 4),
            'extrapolatedPam' => round($extrapolatedPam, 4),
            'dailyReturn' => round($dailyReturn * 100, 5) . '%',
            'dampening' => round($dampening, 3),
            'observedDays' => $observedDays,
            'unobservedDays' => $unobservedDays,
        ]);

        return $extrapolatedPam;
    }

    private function getAccountOpeningDate(string $accountId): ?\DateTimeImmutable
    {
        try {
            $compte = $this->compteService->getCompte($accountId);
            return $compte->getDateOuverture();
        } catch (\Throwable $e) {
            $this->logger->debug('PAM: could not get account opening date', [
                'accountId' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @return array<int, array{date: string, situation: array}>
     */
    private function fetchFullHistory(string $accountId): array
    {
        try {
            $baseUrl = $this->client->getConfiguration()->getComptesApiUrl();
            $endpoint = '/accounts/' . $accountId . '/account-details';
            $dateFrom = (new \DateTime('-2 years'))->format('Y-m-d');
            $dateTo = (new \DateTime())->format('Y-m-d');

            $data = $this->client->get($endpoint, [
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'limit' => 200,
            ], $baseUrl);

            if (empty($data)) {
                return [];
            }

            $items = isset($data[0]) ? $data : [$data];

            $snapshots = [];
            foreach ($items as $item) {
                if (!isset($item['referenceDate'], $item['situation'])) {
                    continue;
                }
                $snapshots[] = [
                    'date' => $item['referenceDate'],
                    'situation' => $item['situation'],
                ];
            }

            return $snapshots;
        } catch (\Throwable $e) {
            $this->logger->warning('PAM: failed to fetch history', [
                'accountId' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
