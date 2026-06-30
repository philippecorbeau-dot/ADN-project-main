<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ProductAccount;
use App\Entity\User\User;
use App\Integration\O2S\Config\O2SConfiguration;
use App\Integration\O2S\Service\CompteServiceInterface;
use App\Integration\O2S\Service\ContactServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Préchauffe le cache O2S de tous les utilisateurs liés à Harvest.
 *
 * Pour chaque utilisateur :
 *  - Purge les anciens caches (compte, account-details, history, patrimoine)
 *  - Rappelle les 3 méthodes API qui sont lentes au runtime, ce qui les remet
 *    en cache avec des données fraîches valables 24 h.
 *
 * Effet : quand le client ouvre son dashboard ADN dans la journée, toutes les
 * données enrichies (versements, historiques 6 mois, patrimoine global) sont
 * déjà en cache → réponse en quelques dizaines de ms au lieu de 30-120 s.
 *
 * Conçu pour être lancé une fois par jour (cron OVH `o2s_warm_cache.php`),
 * en début de journée (ex. 6 h), après o2s_full (4 h) et o2s_fix_emails (5 h).
 */
#[AsCommand(
    name: 'o2s:warm-cache',
    description: 'Préchauffe le cache O2S de tous les users (versements, historiques 6 mois, patrimoine)',
)]
class O2SWarmCacheCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompteServiceInterface $compteService,
        private readonly ContactServiceInterface $contactService,
        private readonly CacheInterface $o2sCache,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user-id', 'u', InputOption::VALUE_REQUIRED, 'Préchauffer uniquement le cache d\'un utilisateur')
            ->addOption('skip-history', null, InputOption::VALUE_NONE, 'Ne pas précharger l\'historique 6 mois (plus rapide)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limiter le nombre d\'utilisateurs traités (test ou batch HTTP)')
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'Décalage de départ (pagination pour batches HTTP)', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $start = microtime(true);

        $userId = $input->getOption('user-id');
        $skipHistory = (bool) $input->getOption('skip-history');
        $limit = $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null;
        $offset = max(0, (int) $input->getOption('offset'));

        $io->title('O2S — Préchauffage du cache (versements / historiques / patrimoine)');

        $qb = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.o2sContactId IS NOT NULL')
            ->orderBy('u.id', 'ASC');

        if ($userId) {
            $qb->andWhere('u.id = :uid')->setParameter('uid', (int) $userId);
        }

        if ($offset > 0) {
            $qb->setFirstResult($offset);
        }

        if ($limit !== null && $limit > 0) {
            $qb->setMaxResults($limit);
        }

        /** @var User[] $users */
        $users = $qb->getQuery()->getResult();
        $total = count($users);

        if ($total === 0) {
            $io->warning('Aucun utilisateur lié à O2S à préchauffer.');
            return Command::SUCCESS;
        }

        $io->text(sprintf('%d utilisateur(s) à traiter%s', $total, $skipHistory ? ' (sans historique)' : ''));

        $stats = [
            'users_done' => 0,
            'users_with_errors' => 0,
            'comptes_warmed' => 0,
            'history_warmed' => 0,
            'patrimoine_warmed' => 0,
            'cache_keys_purged' => 0,
            'errors' => [],
        ];

        $progress = $io->createProgressBar($total);
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $progress->setMessage('Démarrage…');
        $progress->start();

        $dateFrom = (new \DateTime('-6 months'))->format('Y-m-d');
        $dateTo = (new \DateTime())->format('Y-m-d');

        foreach ($users as $user) {
            $userOk = true;
            $progress->setMessage(sprintf('User #%d %s', $user->getId(), $user->getEmail() ?? '?'));

            try {
                $contactId = $user->getO2sContactId();

                $accounts = $this->entityManager->getRepository(ProductAccount::class)
                    ->createQueryBuilder('p')
                    ->where('p.user = :u')
                    ->andWhere('p.o2sCompteId IS NOT NULL')
                    ->setParameter('u', $user)
                    ->getQuery()
                    ->getResult();

                /** @var ProductAccount $account */
                foreach ($accounts as $account) {
                    $compteId = $account->getO2sCompteId();

                    $compteKey = O2SConfiguration::CACHE_KEY_PREFIX . 'compte_' . md5($compteId);
                    $detailsKey = O2SConfiguration::CACHE_KEY_PREFIX . 'acct_details_' . md5($compteId);
                    $historyKey = O2SConfiguration::CACHE_KEY_PREFIX . 'acct_hist_' . md5($compteId . $dateFrom . $dateTo);

                    foreach ([$compteKey, $detailsKey, $historyKey] as $k) {
                        if ($this->o2sCache->delete($k)) {
                            $stats['cache_keys_purged']++;
                        }
                    }

                    try {
                        $this->compteService->getCompte($compteId);
                        $stats['comptes_warmed']++;
                    } catch (\Throwable $e) {
                        $userOk = false;
                        $stats['errors'][] = sprintf('User #%d compte %s getCompte: %s', $user->getId(), $compteId, $e->getMessage());
                    }

                    try {
                        $this->compteService->getAccountDetails($compteId);
                    } catch (\Throwable $e) {
                        // pas bloquant : le contrat n'a peut-être pas d'account-details
                    }

                    if (!$skipHistory) {
                        try {
                            $this->compteService->getAccountDetailsHistory($compteId, $dateFrom, $dateTo);
                            $stats['history_warmed']++;
                        } catch (\Throwable $e) {
                            // historique optionnel
                        }
                    }
                }

                $patrimoineKey = O2SConfiguration::CACHE_KEY_PREFIX . 'patrimoine_' . md5($contactId);
                if ($this->o2sCache->delete($patrimoineKey)) {
                    $stats['cache_keys_purged']++;
                }

                try {
                    $this->contactService->getContactPatrimoine($contactId);
                    $stats['patrimoine_warmed']++;
                } catch (\Throwable $e) {
                    $userOk = false;
                    $stats['errors'][] = sprintf('User #%d patrimoine: %s', $user->getId(), $e->getMessage());
                }

                $stats['users_done']++;
                if (!$userOk) {
                    $stats['users_with_errors']++;
                }

            } catch (\Throwable $e) {
                $stats['users_with_errors']++;
                $stats['errors'][] = sprintf('User #%d: %s', $user->getId(), $e->getMessage());
                $this->logger->error('Cache warming failed for user', [
                    'userId' => $user->getId(),
                    'error' => $e->getMessage(),
                ]);
            }

            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        $duration = round(microtime(true) - $start, 1);

        $io->section('Résumé');
        $io->table(['Statistique', 'Valeur'], [
            ['Utilisateurs traités', $stats['users_done'] . ' / ' . $total],
            ['Utilisateurs avec erreur(s)', $stats['users_with_errors']],
            ['Caches purgés (delete)', $stats['cache_keys_purged']],
            ['Comptes préchauffés (/comptes)', $stats['comptes_warmed']],
            ['Historiques préchauffés (6 mois)', $stats['history_warmed']],
            ['Patrimoines préchauffés', $stats['patrimoine_warmed']],
            ['Erreurs cumulées', count($stats['errors'])],
            ['Durée totale', $duration . ' s'],
        ]);

        if (!empty($stats['errors'])) {
            $shown = array_slice($stats['errors'], 0, 10);
            $io->section(sprintf('Erreurs (%d au total, %d affichées)', count($stats['errors']), count($shown)));
            foreach ($shown as $err) {
                $io->text('  ⚠ ' . $err);
            }
        }

        $io->success(sprintf('Cache préchauffé pour %d utilisateur(s) en %s s', $stats['users_done'], $duration));

        return $stats['users_with_errors'] > $stats['users_done'] / 2
            ? Command::FAILURE
            : Command::SUCCESS;
    }
}
