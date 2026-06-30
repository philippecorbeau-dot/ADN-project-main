<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ProductAccount;
use App\Integration\O2S\Service\AssetServiceInterface;
use App\Integration\O2S\Service\CompteServiceInterface;
use App\Service\MarketData\LiveQuoteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Préchauffe le cache des VL Boursorama pour tous les ISIN détenus par les clients.
 *
 * Méthode :
 *  1. Parcourt tous les `ProductAccount` ayant un `o2s_compte_id`
 *  2. Pour chaque, récupère la situation O2S (déjà en cache)
 *  3. Collecte les ISIN distincts
 *  4. Pour chacun, appelle {@see LiveQuoteService::getLiveNavEur()} (qui met en cache 6 h)
 *
 * Résultat : quand le client ouvre son détail produit, les VL Boursorama sont
 * déjà en mémoire/cache → réponse en ~50 ms au lieu de 5-10 s.
 *
 * À brancher sur cron OVH `cron/ovh/o2s_warm_cache.php` ou en cron horaire dédié
 * (les VL Bourso étant publiées J+1 ouvré, un refresh une fois par jour suffit).
 *
 * Exemples :
 *   php bin/console app:vl-warm-cache                # tous les comptes
 *   php bin/console app:vl-warm-cache --limit=100   # batch (pour découper sur OVH mutualisé)
 *   php bin/console app:vl-warm-cache --offset=100 --limit=100
 */
#[AsCommand(
    name: 'app:vl-warm-cache',
    description: 'Préchauffe le cache Boursorama des VL pour tous les ISIN actifs en portefeuille.',
)]
final class VlWarmCacheCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompteServiceInterface $compteService,
        private readonly AssetServiceInterface $assetService,
        private readonly LiveQuoteService $liveQuoteService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximum de comptes à traiter', null)
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'Décalage de départ (pagination)', '0')
            ->addOption('throttle-ms', null, InputOption::VALUE_REQUIRED, 'Pause entre 2 fetchs Boursorama (ms)', '500')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Liste les ISIN sans appeler Boursorama');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $start = microtime(true);

        $limit = $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null;
        $offset = max(0, (int) $input->getOption('offset'));
        $throttleMs = max(0, (int) $input->getOption('throttle-ms'));
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Préchauffage cache VL Boursorama');

        // 1. Récupérer tous les comptes O2S à scanner
        $qb = $this->entityManager->getRepository(ProductAccount::class)
            ->createQueryBuilder('p')
            ->where('p.o2sCompteId IS NOT NULL')
            ->orderBy('p.id', 'ASC')
            ->setFirstResult($offset);
        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        /** @var ProductAccount[] $accounts */
        $accounts = $qb->getQuery()->getResult();

        $io->writeln(sprintf(' Comptes à scanner : <info>%d</info> (offset %d)', count($accounts), $offset));

        // 2. Collecter les ISIN distincts via getAccountDetails (déjà caché 24 h)
        $isinSet = [];
        foreach ($accounts as $account) {
            try {
                $details = $this->compteService->getAccountDetails($account->getO2sCompteId());
            } catch (\Throwable $e) {
                $io->writeln(sprintf('   ⚠ Skip %s : %s', $account->getO2sCompteId(), $e->getMessage()));
                continue;
            }
            foreach ($details->getSituation() as $line) {
                $isin = $line->getIsin();
                if (!$isin && $line->getAssetId()) {
                    // Résolution paresseuse via AssetService si manquant
                    try {
                        $asset = $this->assetService->getAsset($line->getAssetId());
                        $isin = $asset?->getIsin();
                    } catch (\Throwable) {
                        $isin = null;
                    }
                }
                if ($isin && preg_match('/^[A-Z]{2}[A-Z0-9]{9}\d$/', $isin)) {
                    $isinSet[$isin] = true;
                }
            }
        }
        $isins = array_keys($isinSet);
        sort($isins);

        $io->writeln(sprintf(' ISIN distincts détectés : <info>%d</info>', count($isins)));

        if ($dryRun) {
            $io->section('ISIN (dry-run, aucun appel Boursorama)');
            foreach ($isins as $isin) {
                $io->writeln('  - ' . $isin);
            }
            return Command::SUCCESS;
        }

        // 3. Préchauffer en appelant LiveQuoteService (qui cache 6 h via Symfony cache.app)
        $io->section('Préchauffage');
        $progress = $io->createProgressBar(count($isins));
        $progress->start();

        $stats = ['boursorama' => 0, 'boursorama+fx' => 0, 'fallback' => 0, 'none' => 0];

        foreach ($isins as $isin) {
            try {
                $r = $this->liveQuoteService->getLiveNavEur($isin);
                if ($r['nav'] === null) {
                    $stats['none']++;
                } elseif (str_starts_with((string) $r['source'], 'boursorama')) {
                    $key = $r['isConverted'] ? 'boursorama+fx' : 'boursorama';
                    $stats[$key]++;
                } else {
                    $stats['fallback']++;
                }
            } catch (\Throwable) {
                $stats['none']++;
            }
            $progress->advance();
            if ($throttleMs > 0) {
                usleep($throttleMs * 1000);
            }
        }
        $progress->finish();
        $io->newLine(2);

        $io->success(sprintf('Préchauffage terminé en %.1f s', microtime(true) - $start));
        $io->table(
            ['Source', 'Nombre d\'ISIN'],
            [
                ['Boursorama (EUR direct)', (string) $stats['boursorama']],
                ['Boursorama + FX BCE', (string) $stats['boursorama+fx']],
                ['Fallback (TwelveData/Yahoo)', (string) $stats['fallback']],
                ['Non résolu', (string) $stats['none']],
            ]
        );

        return Command::SUCCESS;
    }
}
