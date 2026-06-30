<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ProductAccount;
use App\Integration\O2S\Client\O2SClientInterface;
use App\Integration\O2S\Config\O2SConfiguration;
use App\Integration\O2S\Service\AssetServiceInterface;
use App\Integration\O2S\Service\CompteService;
use App\Integration\O2S\Service\ContactServiceInterface;
use App\Integration\O2S\Service\ProductServiceInterface;
use App\Integration\O2S\Sync\O2SSyncService;
use App\Service\MarketData\TwelveDataClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Cache\CacheInterface;

#[AsCommand(
    name: 'o2s:test',
    description: 'Test the O2S API connection and fetch sample data',
)]
class O2STestCommand extends Command
{
    public function __construct(
        private readonly O2SClientInterface $o2sClient,
        private readonly ContactServiceInterface $contactService,
        private readonly CompteService $compteService,
        private readonly AssetServiceInterface $assetService,
        private readonly ProductServiceInterface $productService,
        private readonly EntityManagerInterface $entityManager,
        private readonly O2SSyncService $o2sSyncService,
        private readonly TwelveDataClient $twelveDataClient,
        private readonly CacheInterface $o2sCache,
        private readonly \App\Service\MarketData\QuoteAggregator $quoteAggregator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('contacts', 'c', InputOption::VALUE_NONE, 'Fetch sample contacts')
            ->addOption('comptes', null, InputOption::VALUE_NONE, 'Fetch sample comptes')
            ->addOption('contact-id', null, InputOption::VALUE_REQUIRED, 'Fetch a specific contact by ID')
            ->addOption('account-details', null, InputOption::VALUE_REQUIRED, 'Fetch account details (valorisation) by account ID')
            ->addOption('asset', null, InputOption::VALUE_REQUIRED, 'Fetch asset details by asset ID')
            ->addOption('raw-compte', null, InputOption::VALUE_REQUIRED, 'Dump raw API response for a specific compte ID')
            ->addOption('product-types', null, InputOption::VALUE_NONE, 'List all unique product types from Products API')
            ->addOption('history', null, InputOption::VALUE_REQUIRED, 'Fetch historical account-details (6 months) by account ID')
            ->addOption('resync-user', null, InputOption::VALUE_REQUIRED, 'Re-sync all O2S valuations for a specific user ID (fixes doubling bug)')
            ->addOption('patrimoine', null, InputOption::VALUE_REQUIRED, 'Fetch patrimoine data from GET /contacts/{contactId} and dump raw JSON')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit number of results', 5)
            ->addOption('raw-account-details', null, InputOption::VALUE_REQUIRED, 'Dump RAW JSON from /account-details')
            ->addOption('raw-contact', null, InputOption::VALUE_REQUIRED, 'Dump raw contact JSON (informationsCommerciales, etc.)')
            ->addOption('type-contacts-stats', null, InputOption::VALUE_NONE, 'Count typeContact (Client/Prospect) for all O2S contacts')
            ->addOption('explore-api', null, InputOption::VALUE_REQUIRED, 'Explore undocumented API endpoints for a compte ID')
            ->addOption('test-quotes', null, InputOption::VALUE_REQUIRED, 'Test QuoteAggregator for all ISINs in an account')
            ->addOption('test-refresh', null, InputOption::VALUE_REQUIRED, 'Test refresh/situation endpoints for an account')
            ->addOption('test-dated-details', null, InputOption::VALUE_REQUIRED, 'Test GET /account-details/{date} for multiple recent dates (accountId)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $fetchContacts = $input->getOption('contacts');
        $fetchComptes = $input->getOption('comptes');
        $contactId = $input->getOption('contact-id');
        $accountDetailsId = $input->getOption('account-details');
        $assetId = $input->getOption('asset');
        $rawCompteId = $input->getOption('raw-compte');
        $productTypes = $input->getOption('product-types');
        $limit = (int) $input->getOption('limit');

        $io->title('O2S API Test');

        // Display configuration (masked)
        $config = $this->o2sClient->getConfiguration();
        $io->section('Configuration');
        $io->table(
            ['Parameter', 'Value'],
            [
                ['Environment', $config->getEnvironment()],
                ['API URL', $config->getApiUrl()],
                ['Auth URL', $config->getAuthUrl()],
                ['Client ID', $this->maskString($config->getClientId())],
                ['Username', $this->maskString($config->getUsername())],
                ['Is Complete', $config->isComplete() ? 'Yes' : 'No'],
            ]
        );

        // Test connection
        $io->section('Connection Test');
        $io->text('Testing authentication...');

        try {
            if (!$this->o2sClient->testConnection()) {
                $io->error('Connection test failed');
                return Command::FAILURE;
            }
            $io->success('Authentication successful!');
        } catch (\Throwable $e) {
            $io->error(sprintf('Connection failed: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        // List all product types
        if ($productTypes) {
            $io->section('Product Types from Products API');
            try {
                $products = $this->productService->getAllProducts();
                $typesCounts = [];
                foreach ($products as $product) {
                    $type = $product->getType() ?? 'NULL';
                    $typesCounts[$type] = ($typesCounts[$type] ?? 0) + 1;
                }
                arsort($typesCounts);
                $rows = [];
                foreach ($typesCounts as $type => $count) {
                    $rows[] = [$type, $count];
                }
                $io->table(['Type', 'Count'], $rows);
                $io->text(sprintf('Total products: %d, Unique types: %d', count($products), count($typesCounts)));
                
                // Check products map
                $map = $this->productService->getProductsMap();
                $io->text(sprintf('Products map keys count: %d', count($map)));
                
                // Show sample keys
                $sampleKeys = array_slice(array_keys($map), 0, 5);
                $io->text(sprintf('Sample map keys: %s', implode(', ', $sampleKeys)));
                
                // Test specific lookup
                if (isset($map['3567'])) {
                    $io->text(sprintf('Product 3567 found: %s (type: %s)', $map['3567']->getLabel(), $map['3567']->getType()));
                } else {
                    $io->warning('Product 3567 NOT found in map!');
                    // Try to find it
                    foreach ($map as $key => $p) {
                        if (strpos((string) $key, '3567') !== false || strpos($p->getLabel() ?? '', 'PEI') !== false) {
                            $io->text(sprintf('  Found related: key=%s label=%s type=%s', $key, $p->getLabel(), $p->getType()));
                            break;
                        }
                    }
                }
                if (isset($map['17809'])) {
                    $io->text(sprintf('Product 17809 found: %s (type: %s)', $map['17809']->getLabel(), $map['17809']->getType()));
                } else {
                    $io->warning('Product 17809 NOT found in map!');
                }
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed: %s', $e->getMessage()));
            }
        }

        // Dump raw compte data
        if ($rawCompteId) {
            $io->section(sprintf('Raw Compte Data: %s', $rawCompteId));
            try {
                $compte = $this->compteService->getCompte($rawCompteId);
                $io->writeln(json_encode($compte->getRawData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $io->newLine();
                $io->text(sprintf('ModeleFinancier: %s', $compte->getModeleFinancier() ?? 'NULL'));
                $io->text(sprintf('Type: %s', $compte->getType() ?? 'NULL'));
                $io->text(sprintf('ProductType (mapped): %s', $compte->getProductType()));
                $io->text(sprintf('Montant: %s', $compte->getMontant() !== null ? number_format($compte->getMontant(), 2) : 'NULL'));
                $io->text(sprintf('Libelle: %s', $compte->getLibelle() ?? 'NULL'));
                $io->text(sprintf('ProduitId: %s', $compte->getProduitId() ?? 'NULL'));
                
                // Try to look up product type
                if ($compte->getProduitId()) {
                    try {
                        $product = $this->productService->getProduct($compte->getProduitId());
                        $io->text(sprintf('Product Label: %s', $product->getLabel() ?? 'NULL'));
                        $io->text(sprintf('Product Type (API): %s (%s)', $product->getType() ?? 'NULL', $product->getTypeLabel()));
                        $io->text(sprintf('Product InstitutionId: %s', $product->getInstitutionId() ?? 'NULL'));
                    } catch (\Throwable $pe) {
                        $io->text(sprintf('Failed to look up product: %s', $pe->getMessage()));
                    }
                }
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed: %s', $e->getMessage()));
            }
        }

        // Test asset endpoint
        if ($assetId) {
            $io->section(sprintf('Asset Details: %s', $assetId));
            try {
                $asset = $this->assetService->getAsset($assetId);
                
                $io->table(
                    ['Field', 'Value'],
                    [
                        ['Asset ID', $asset->getAssetId()],
                        ['Label', $asset->getLabel() ?? '-'],
                        ['ISIN', $asset->getIsin() ?? '-'],
                        ['Currency', $asset->getCurrency() ?? '-'],
                        ['Asset Type', $asset->getAssetType() ?? '-'],
                        ['Asset Class', $asset->getAssetClass() ?? '-'],
                        ['Management Company', $asset->getManagementCompany() ?? '-'],
                    ]
                );

                $io->success('Asset details fetched successfully!');
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to fetch asset: %s', $e->getMessage()));
            }
        }

        // Test account-details endpoint (valorisation)
        if ($accountDetailsId) {
            $io->section(sprintf('Account Details (Valorisation): %s', $accountDetailsId));
            try {
                $details = $this->compteService->getAccountDetails($accountDetailsId);
                
                $io->table(
                    ['Field', 'Value'],
                    [
                        ['Account ID', $details->getAccountId()],
                        ['Total Value', $details->getTotalValue() !== null ? number_format($details->getTotalValue(), 2, ',', ' ') . ' €' : '-'],
                        ['Liquidity', $details->getLiquidity() !== null ? number_format($details->getLiquidity(), 2, ',', ' ') . ' €' : '-'],
                        ['Valuation Date', $details->getValuationDate()?->format('Y-m-d') ?? '-'],
                        ['Has Valuation', $details->hasValuation() ? 'Yes' : 'No'],
                    ]
                );

                // Display situation (asset lines) if available
                $situation = $details->getSituation();
                if (!empty($situation)) {
                    $io->section('Asset Lines (Situation)');
                    $rows = [];
                    foreach ($situation as $asset) {
                        $rows[] = [
                            $asset->getAssetName() ?? '-',
                            $asset->getIsin() ?? '-',
                            $asset->getQuantity() !== null ? number_format($asset->getQuantity(), 4, ',', ' ') : '-',
                            $asset->getNetAssetValue() !== null ? number_format($asset->getNetAssetValue(), 2, ',', ' ') . ' €' : '-',
                            $asset->getValue() !== null ? number_format($asset->getValue(), 2, ',', ' ') . ' €' : '-',
                            $asset->getAssetType() ?? '-',
                        ];
                    }
                    $io->table(['Name', 'ISIN', 'Quantity', 'NAV', 'Value', 'Type'], $rows);
                } else {
                    $io->info('No asset lines found in situation');
                }

                $io->success('Account details fetched successfully!');
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to fetch account details: %s', $e->getMessage()));
            }
        }

        // Fetch specific contact
        if ($contactId) {
            $io->section(sprintf('Contact Details: %s', $contactId));
            try {
                $contact = $this->contactService->getContact($contactId);
                $io->table(
                    ['Field', 'Value'],
                    [
                        ['ID', $contact->getId()],
                        ['Civilité', $contact->getCivilite() ?? '-'],
                        ['Nom', $contact->getNom() ?? '-'],
                        ['Prénom', $contact->getPrenom() ?? '-'],
                        ['Email', $contact->getEmail() ?? '-'],
                        ['Téléphone', $contact->getTelephone() ?? '-'],
                        ['Mobile', $contact->getTelephoneMobile() ?? '-'],
                        ['Date de naissance', $contact->getDateNaissance()?->format('Y-m-d') ?? '-'],
                        ['Profession', $contact->getProfession() ?? '-'],
                        ['Date création', $contact->getDateCreation()?->format('Y-m-d H:i:s') ?? '-'],
                    ]
                );

                // Fetch comptes for this contact
                $io->text('Fetching comptes for this contact...');
                $comptes = $this->compteService->getComptesForContact($contactId);
                
                if (!empty($comptes)) {
                    $io->section('Comptes for contact');
                    $rows = [];
                    foreach ($comptes as $compte) {
                        $rows[] = [
                            $compte->getId(),
                            $compte->getDisplayName(),
                            $compte->getProductType(),
                            $compte->getStatut() ?? '-',
                            $compte->getMontant() !== null ? number_format($compte->getMontant(), 2, ',', ' ') . ' €' : '-',
                            $compte->getDateValeur()?->format('Y-m-d') ?? '-',
                        ];
                    }
                    $io->table(['ID', 'Nom', 'Type', 'Statut', 'Montant', 'Date Valeur'], $rows);

                    // Summary
                    $summary = $this->compteService->calculateSummary($comptes);
                    $io->text(sprintf(
                        'Total: %s € (%d comptes)',
                        number_format($summary['total_valuation'], 2, ',', ' '),
                        $summary['compte_count']
                    ));
                } else {
                    $io->info('No comptes found for this contact');
                }

            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to fetch contact: %s', $e->getMessage()));
            }
        }

        // Fetch sample contacts
        if ($fetchContacts) {
            $io->section('Sample Contacts');
            try {
                $contacts = $this->contactService->getContacts([], $limit);
                
                if (empty($contacts)) {
                    $io->info('No contacts found');
                } else {
                    $rows = [];
                    foreach ($contacts as $contact) {
                        $rows[] = [
                            $contact->getId(),
                            $contact->getFullName(),
                            $contact->getEmail() ?? '-',
                            $contact->getDateCreation()?->format('Y-m-d') ?? '-',
                        ];
                    }
                    $io->table(['ID', 'Nom', 'Email', 'Date création'], $rows);
                    $io->success(sprintf('Fetched %d contacts', count($contacts)));
                }
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to fetch contacts: %s', $e->getMessage()));
            }
        }

        // Fetch sample comptes (requires a contact, so we fetch one first)
        if ($fetchComptes) {
            $io->section('Sample Comptes');
            try {
                // First, get a sample contact to use for fetching comptes
                $io->text('Fetching a sample contact to retrieve comptes...');
                $contacts = $this->contactService->getContacts([], 1);
                
                if (empty($contacts)) {
                    $io->warning('No contacts found. Cannot fetch comptes without a contactId.');
                } else {
                    $sampleContact = $contacts[0];
                    $io->text(sprintf('Using contact: %s (%s)', $sampleContact->getFullName(), $sampleContact->getId()));
                    
                    $comptes = $this->compteService->getComptesForContact($sampleContact->getId());
                    
                    if (empty($comptes)) {
                        $io->info('No comptes found for this contact');
                    } else {
                        $rows = [];
                        foreach (array_slice($comptes, 0, $limit) as $compte) {
                            $rows[] = [
                                $compte->getId(),
                                $compte->getDisplayName(),
                                $compte->getProductType(),
                                $compte->getMontant() !== null ? number_format($compte->getMontant(), 2, ',', ' ') . ' €' : '-',
                            ];
                        }
                        $io->table(['ID', 'Nom', 'Type', 'Montant'], $rows);
                        $io->success(sprintf('Fetched %d comptes (showing %d)', count($comptes), min(count($comptes), $limit)));
                    }
                }
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to fetch comptes: %s', $e->getMessage()));
            }
        }

        // Raw account-details JSON dump
        $rawAccountDetailsId = $input->getOption('raw-account-details');
        if ($rawAccountDetailsId) {
            $io->section('Raw /account-details for: ' . $rawAccountDetailsId);
            try {
                $config = $this->o2sClient->getConfiguration();
                $baseUrl = $config->getComptesApiUrl();
                $endpoint = '/accounts/' . $rawAccountDetailsId . '/account-details';
                $data = $this->o2sClient->get($endpoint, [], $baseUrl);
                $io->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $e) {
                $io->error('Failed: ' . $e->getMessage());
            }
        }

        // Historical account-details
        $historyId = $input->getOption('history');
        if ($historyId) {
            $io->section('Historical Account Details: ' . $historyId);
            try {
                $config = $this->o2sClient->getConfiguration();
                $baseUrl = $config->getComptesApiUrl();
                $endpoint = '/accounts/' . $historyId . '/account-details';
                $dateFrom = (new \DateTime('-6 months'))->format('Y-m-d');
                $dateTo = (new \DateTime())->format('Y-m-d');

                $io->text("Fetching history: $dateFrom to $dateTo");
                $data = $this->o2sClient->get($endpoint, [
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                    'limit' => 100,
                ], $baseUrl);

                if (empty($data)) {
                    $io->warning('No historical data returned');
                } else {
                    $isArray = isset($data[0]);
                    $items = $isArray ? $data : [$data];
                    $io->text(sprintf('Returned %d historical entries', count($items)));

                    $rows = [];
                    foreach ($items as $item) {
                        $rows[] = [
                            $item['referenceDate'] ?? 'N/A',
                            isset($item['totalValue']) ? number_format((float)$item['totalValue'], 2, ',', ' ') . ' €' : 'N/A',
                            isset($item['liquidity']) ? number_format((float)$item['liquidity'], 2, ',', ' ') . ' €' : 'N/A',
                            $item['view'] ?? 'N/A',
                            isset($item['situation']) ? count($item['situation']) . ' lines' : '0',
                        ];
                    }
                    $io->table(['Date', 'Total Value', 'Liquidity', 'View', 'Situation'], $rows);

                    // Track quantity evolution per asset
                    $assetHistory = [];
                    foreach ($items as $item) {
                        $date = $item['referenceDate'] ?? '?';
                        foreach ($item['situation'] ?? [] as $line) {
                            $aid = $line['assetId'] ?? '?';
                            $assetHistory[$aid][] = [
                                'date' => $date,
                                'qty' => (float)($line['quantity'] ?? 0),
                                'nav' => (float)($line['netAssetValue'] ?? 0),
                                'value' => (float)($line['value'] ?? 0),
                                'avgPrice' => $line['averagePriceValue'] ?? null,
                                'avgPriceType' => $line['averagePriceType'] ?? null,
                            ];
                        }
                    }

                    foreach ($assetHistory as $aid => $snapshots) {
                        $io->section("Asset $aid — Quantity evolution");
                        $aRows = [];
                        $prev = null;
                        foreach ($snapshots as $s) {
                            $delta = $prev !== null ? $s['qty'] - $prev : 0.0;
                            $deltaStr = $prev === null ? '-' : ($delta != 0 ? sprintf('%+.4f', $delta) : '=');
                            $aRows[] = [
                                $s['date'],
                                number_format($s['qty'], 4, ',', ' '),
                                number_format($s['nav'], 4, ',', ' ') . ' €',
                                number_format($s['value'], 2, ',', ' ') . ' €',
                                $deltaStr,
                                $s['avgPrice'] !== null ? number_format((float)$s['avgPrice'], 4, ',', ' ') : 'N/A',
                                $s['avgPriceType'] ?? 'N/A',
                            ];
                            $prev = $s['qty'];
                        }
                        $io->table(['Date', 'Qty', 'NAV', 'Value', 'Δ Qty', 'avgPrice', 'Type'], $aRows);
                    }
                }
            } catch (\Throwable $e) {
                $io->error('Failed: ' . $e->getMessage());
            }
        }

        // Patrimoine: fetch and parse patrimoine from GET /contacts/{contactId}
        $patrimoineContactId = $input->getOption('patrimoine');
        if ($patrimoineContactId) {
            $io->section('Patrimoine — Contact: ' . $patrimoineContactId);
            try {
                // Parse via PatrimoineDTO
                $patrimoine = $this->contactService->getContactPatrimoine($patrimoineContactId);
                
                if ($patrimoine->hasData()) {
                    $io->table(
                        ['Catégorie', 'Montant'],
                        [
                            ['🏠 Immobilier total', number_format($patrimoine->getImmobilier(), 2, ',', ' ') . ' €'],
                            ['   ↳ Résidence principale', number_format($patrimoine->getImmobilierResidencePrincipale(), 2, ',', ' ') . ' €'],
                            ['   ↳ Résidence secondaire', number_format($patrimoine->getImmobilierResidenceSecondaire(), 2, ',', ' ') . ' €'],
                            ['   ↳ Locatif', number_format($patrimoine->getImmobilierLocatif(), 2, ',', ' ') . ' €'],
                            ['   ↳ Parts de SCPI', number_format($patrimoine->getImmobilierSCPI(), 2, ',', ' ') . ' €'],
                            ['   ↳ Autre', number_format($patrimoine->getImmobilierAutre(), 2, ',', ' ') . ' €'],
                            ['💰 Actifs financiers', number_format($patrimoine->getActifsFinanciers(), 2, ',', ' ') . ' €'],
                            ['📊 Total Actif', number_format($patrimoine->getTotalActif(), 2, ',', ' ') . ' €'],
                            ['📉 Total Passif', number_format($patrimoine->getTotalPassif(), 2, ',', ' ') . ' €'],
                            ['🏦 Patrimoine Net', number_format($patrimoine->getPatrimoineNet(), 2, ',', ' ') . ' €'],
                        ]
                    );

                    $pcts = $patrimoine->getDonutPercentages();
                    $io->text(sprintf('Répartition: Immobilier %d%% / Financier %d%%', $pcts['immobilier_pct'], $pcts['financier_pct']));
                    $io->text(sprintf('Profession: %s', $patrimoine->getProfession() ?? 'N/A'));
                    $io->text(sprintf('Situation familiale: %s', $patrimoine->getSituationFamiliale() ?? 'N/A'));
                    $io->text(sprintf('Nombre de stocks: %d', $patrimoine->getStockCount()));

                    // Détail par stock
                    $io->section('Détail des stocks');
                    $rows = [];
                    foreach ($patrimoine->getStocksDetail() as $s) {
                        $rows[] = [
                            $s['libelle'],
                            $s['categorie'],
                            number_format($s['valeur'], 2, ',', ' ') . ' €',
                            $s['part'] . '%',
                            number_format($s['valeurDetenue'], 2, ',', ' ') . ' €',
                        ];
                    }
                    $io->table(['Libellé', 'Catégorie', 'Valeur brute', 'Part', 'Valeur détenue'], $rows);

                    $io->success('Patrimoine parsed successfully!');
                } else {
                    $io->warning('Aucune donnée patrimoine trouvée pour ce contact (stocks vide).');
                    
                    // Dump top-level keys for debugging
                    $rawData = $patrimoine->getRawData();
                    $io->text('Clés de premier niveau:');
                    $io->listing(array_keys($rawData));
                }
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to fetch patrimoine: %s', $e->getMessage()));
            }
        }

        // Re-sync valuations for a specific user (fix doubling bug)
        $resyncUserId = $input->getOption('resync-user');
        if ($resyncUserId) {
            $io->section('Re-syncing O2S valuations for user ID: ' . $resyncUserId);

            $accounts = $this->entityManager->getRepository(ProductAccount::class)
                ->createQueryBuilder('p')
                ->where('p.user = :uid')
                ->andWhere('p.o2sCompteId IS NOT NULL')
                ->setParameter('uid', (int) $resyncUserId)
                ->getQuery()
                ->getResult();

            $io->text(sprintf('Found %d O2S accounts', count($accounts)));
            $rows = [];
            $totalOld = 0.0;
            $totalNew = 0.0;

            foreach ($accounts as $account) {
                $compteId = $account->getO2sCompteId();
                $oldVal = (float) ($account->getO2sValuation() ?? 0);
                $apiMontant = null;
                $liquidity = 0.0;
                $detailTotalValue = 0.0;
                $newDate = $account->getO2sValuationDate();

                // 1. /comptes/{id} → montant
                try {
                    $compte = $this->compteService->getCompte($compteId);
                    $apiMontant = $compte->getMontant();
                    if ($apiMontant !== null && $apiMontant > 0 && $compte->getDateValeur()) {
                        $newDate = $compte->getDateValeur();
                    }
                } catch (\Throwable $e) {
                    $io->warning(sprintf('  /comptes/ failed for %s: %s', $compteId, $e->getMessage()));
                }

                // 2. /accounts/{id}/account-details → totalValue + liquidity
                try {
                    $details = $this->compteService->getAccountDetails($compteId);
                    if ($details->hasValuation()) {
                        $detailTotalValue = $details->getTotalValue() ?? 0.0;
                        $l = $details->getLiquidity();
                        if ($l !== null && $l > 0) {
                            $liquidity = $l;
                        }
                        if ($apiMontant === null && $details->getValuationDate()) {
                            $newDate = $details->getValuationDate();
                        }
                    }
                } catch (\Throwable $e) {
                    $io->warning(sprintf('  account-details failed for %s: %s', $compteId, $e->getMessage()));
                }

                // 3. Correct evaluation = montant API + liquidity (NOT old stored value + liquidity!)
                if ($apiMontant !== null && $apiMontant > 0) {
                    $evaluation = $apiMontant + $liquidity;
                } else {
                    $evaluation = $detailTotalValue + $liquidity;
                }

                if ($evaluation <= 0 && $oldVal > 0) {
                    $evaluation = $oldVal;
                }

                $diff = $oldVal - $evaluation;
                $status = abs($diff) > 0.01 ? '⚠ CORRECTED' : '✓ OK';

                $rows[] = [
                    $account->getDisplayAlias(),
                    number_format($oldVal, 2, ',', ' ') . ' €',
                    number_format($evaluation, 2, ',', ' ') . ' €',
                    abs($diff) > 0.01 ? number_format($diff, 2, ',', ' ') . ' €' : '-',
                    $apiMontant !== null ? number_format($apiMontant, 2, ',', ' ') : 'NULL',
                    number_format($detailTotalValue, 2, ',', ' '),
                    number_format($liquidity, 2, ',', ' '),
                    $status,
                ];

                $totalOld += $oldVal;
                $totalNew += $evaluation;

                $account->setO2sValuation((string) $evaluation);
                if ($newDate) {
                    $account->setO2sValuationDate($newDate);
                }
                $account->setO2sSyncedAt(new \DateTimeImmutable());
            }

            $io->table(
                ['Produit', 'Ancien', 'Nouveau', 'Diff', 'Montant API', 'TotalValue', 'Liquidity', 'Status'],
                $rows
            );

            $io->text(sprintf('Total ancien: %s €', number_format($totalOld, 2, ',', ' ')));
            $io->text(sprintf('Total nouveau: %s €', number_format($totalNew, 2, ',', ' ')));
            $io->text(sprintf('Différence: %s €', number_format($totalOld - $totalNew, 2, ',', ' ')));

            $this->entityManager->flush();
            $io->success('Valuations re-synced and saved!');
        }

        // Raw contact dump (informationsCommerciales, etc.)
        $rawContactId = $input->getOption('raw-contact');
        if ($rawContactId) {
            $io->section('Raw Contact Data: ' . $rawContactId);
            try {
                $contact = $this->contactService->getContact($rawContactId);
                $raw = $contact->getRawData();
                $ic = $raw['informationsCommerciales'] ?? null;
                $io->text('informationsCommerciales:');
                $io->text(json_encode($ic, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $e) {
                $io->error('Failed: ' . $e->getMessage());
            }
        }

        // Backfill + stats typeContact for all O2S users
        if ($input->getOption('type-contacts-stats')) {
            $io->section('Backfill typeContact (Client/Prospect) pour tous les contacts O2S');

            $stats = $this->o2sSyncService->backfillTypeContacts(
                limit: 0,
                onProgress: function (int $current, int $total) use ($io, &$progressStarted) {
                    if (!isset($progressStarted)) {
                        $io->progressStart($total);
                        $progressStarted = true;
                    }
                    $io->progressAdvance();
                }
            );
            if (isset($progressStarted)) {
                $io->progressFinish();
            }

            $io->table(['Résultat', 'Nombre'], [
                ['Mis à jour', $stats['updated']],
                ['Inchangés', $stats['skipped']],
                ['Erreurs', $stats['errors']],
            ]);

            // Show final counts
            $userRepo = $this->entityManager->getRepository(\App\Entity\User\User::class);
            $clientCount = (int) $userRepo->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.o2sContactId IS NOT NULL')
                ->andWhere('u.o2sTypeContact = :t')->setParameter('t', 'Client')
                ->getQuery()->getSingleScalarResult();
            $prospectCount = (int) $userRepo->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.o2sContactId IS NOT NULL')
                ->andWhere('u.o2sTypeContact = :t')->setParameter('t', 'Prospect')
                ->getQuery()->getSingleScalarResult();
            $nullCount = (int) $userRepo->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u.o2sContactId IS NOT NULL')
                ->andWhere('u.o2sTypeContact IS NULL')
                ->getQuery()->getSingleScalarResult();

            $io->table(['Classification', 'Nombre'], [
                ['Client', $clientCount],
                ['Prospect', $prospectCount],
                ['Non classé', $nullCount],
            ]);

            $io->success('Backfill terminé !');
        }

        $exploreApiId = $input->getOption('explore-api');
        if ($exploreApiId) {
            $this->exploreApiEndpoints($io, $exploreApiId);
        }


        if ($testQuotesId = $input->getOption('test-quotes')) {
            $this->runTestQuotes($io, $testQuotesId);
        }

        if ($testRefreshId = $input->getOption('test-refresh')) {
            $this->runTestRefresh($io, $testRefreshId);
        }

        if ($testDatedId = $input->getOption('test-dated-details')) {
            $this->runTestDatedDetails($io, $testDatedId);
        }

        $io->success('O2S API test completed!');
        return Command::SUCCESS;
    }

    private function runTestQuotes(SymfonyStyle $io, string $accountId): void
    {
        $io->section("QuoteAggregator test for account: $accountId");

        $details = $this->compteService->getAccountDetails($accountId);
        if (!$details) {
            $io->error('No account-details found');
            return;
        }

        $io->text(sprintf('referenceDate (snapshot): %s', $details->getValuationDate()?->format('Y-m-d') ?? 'null'));

        $assetIds = [];
        foreach ($details->getSituation() as $asset) {
            if ($asset->getAssetId()) {
                $assetIds[] = $asset->getAssetId();
            }
        }

        $assetsInfo = !empty($assetIds) ? $this->assetService->getAssets($assetIds) : [];

        $rows = [];
        foreach ($details->getSituation() as $asset) {
            $assetId = $asset->getAssetId();
            $info = $assetsInfo[$assetId] ?? null;
            $isin = $info?->getIsin() ?? $asset->getIsin();
            $name = $info?->getLabel() ?? $asset->getAssetName() ?? '?';
            $apiDate = $asset->getNetAssetValueDate()?->format('Y-m-d') ?? '-';
            $qty = $asset->getQuantity() ?? 0;

            $liveNav = '-';
            $liveDate = '-';
            if ($isin && $qty > 0) {
                try {
                    $q = $this->quoteAggregator->getLast($isin);
                    $liveNav = $q['nav'] !== null ? number_format($q['nav'], 4) : 'FAIL';
                    $liveDate = $q['navDate'] ?? 'FAIL';
                } catch (\Throwable $e) {
                    $liveNav = 'ERR';
                    $liveDate = $e->getMessage();
                }
            }

            $rows[] = [
                substr($name, 0, 30),
                $isin ?? '-',
                $apiDate,
                $liveNav,
                $liveDate,
                $apiDate !== '-' && $liveDate !== '-' && $liveDate !== 'FAIL' && $liveDate > $apiDate ? 'PLUS RECENT' : ($liveDate === $apiDate ? '=' : ''),
            ];
        }

        $io->table(
            ['Fonds', 'ISIN', 'Date API', 'Nav Live', 'Date Live', 'Statut'],
            $rows
        );
    }

    private function exploreApiEndpoints(SymfonyStyle $io, string $compteId): void
    {
        $io->title('Exploration API — Compte: ' . $compteId);
        $config = $this->o2sClient->getConfiguration();
        $baseUrl = $config->getComptesApiUrl();

        $endpoints = [
            '/comptes/' . $compteId . '/operations',
            '/comptes/' . $compteId . '/mouvements',
            '/comptes/' . $compteId . '/transactions',
            '/comptes/' . $compteId . '/versements',
            '/comptes/' . $compteId . '/flux',
            '/comptes/' . $compteId . '/historique',
            '/accounts/' . $compteId . '/operations',
            '/accounts/' . $compteId . '/transactions',
            '/accounts/' . $compteId . '/movements',
            '/accounts/' . $compteId . '/payments',
            '/accounts/' . $compteId . '/flows',
            '/accounts/' . $compteId . '/history',
            '/accounts/' . $compteId . '/positions',
            '/accounts/' . $compteId . '/average-prices',
            '/accounts/' . $compteId . '/pam',
            '/operations?compteId=' . $compteId,
            '/mouvements?compteId=' . $compteId,
            '/accounts/' . $compteId . '/account-details?includeAveragePrice=true',
            '/accounts/' . $compteId . '/account-details?expand=averagePrice',
            '/accounts/' . $compteId . '/account-details?fields=all',
        ];

        $io->text(sprintf('Test de %d endpoints...', count($endpoints)));
        $io->newLine();

        foreach ($endpoints as $endpoint) {
            try {
                $data = $this->o2sClient->get($endpoint, [], $baseUrl);
                $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $io->success("TROUVÉ: $endpoint");
                $io->text(substr($json, 0, 1000));
                $io->newLine();
            } catch (\Throwable $e) {
                $code = 'unknown';
                if (method_exists($e, 'getCode')) {
                    $code = $e->getCode();
                }
                if (str_contains($e->getMessage(), '404')) {
                    $io->text("  ✗ $endpoint → 404 Not Found");
                } elseif (str_contains($e->getMessage(), '405')) {
                    $io->text("  ? $endpoint → 405 Method Not Allowed (endpoint existe mais pas en GET)");
                } elseif (str_contains($e->getMessage(), '403')) {
                    $io->text("  ⚠ $endpoint → 403 Forbidden (endpoint existe mais accès refusé)");
                } elseif (str_contains($e->getMessage(), '400')) {
                    $io->text("  ? $endpoint → 400 Bad Request (endpoint pourrait exister)");
                } else {
                    $io->text("  ✗ $endpoint → " . $e->getMessage());
                }
            }
        }
    }

    private function runTestRefresh(SymfonyStyle $io, string $compteId): void
    {
        $io->section("Test refresh endpoints for: $compteId");
        $config = $this->o2sClient->getConfiguration();
        $baseUrl = $config->getComptesApiUrl();

        $io->text('Dates AVANT refresh:');
        try {
            $data = $this->o2sClient->get('/accounts/' . $compteId . '/account-details', [], $baseUrl);
            $ref = $data[0]['referenceDate'] ?? 'unknown';
            $io->text("  referenceDate = $ref");
        } catch (\Throwable $e) {
            $io->error('Cannot fetch account-details: ' . $e->getMessage());
        }

        $endpoints = [
            ['PUT',  '/comptes/' . $compteId . '/situations', ['situation' => []]],
            ['PUT',  '/comptes/' . $compteId . '/situations', ['refresh' => true]],
            ['PUT',  '/comptes/' . $compteId . '/situations', ['compteId' => $compteId]],
            ['DELETE', '/comptes/' . $compteId . '/situations'],
            ['GET',  '/comptes/' . $compteId . '/aggregation-status'],
            ['POST', '/comptes/' . $compteId . '/demande-aggregation'],
            ['GET',  '/comptes/' . $compteId . '/derniere-situation'],
        ];

        foreach ($endpoints as $ep) {
            $method = $ep[0];
            $url = $ep[1];
            $body = $ep[2] ?? [];
            try {
                if ($method === 'POST') {
                    $data = $this->o2sClient->post($url, $body, $baseUrl);
                } elseif ($method === 'PUT') {
                    $data = $this->o2sClient->put($url, $body, $baseUrl);
                } elseif ($method === 'DELETE') {
                    $this->o2sClient->delete($url, $baseUrl);
                    $data = ['deleted' => true];
                } else {
                    $data = $this->o2sClient->get($url, [], $baseUrl);
                }
                $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $io->success("$method $url => OK");
                $io->text(substr($json, 0, 500));
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $code = 'ERR';
                if (str_contains($msg, '404')) $code = '404';
                elseif (str_contains($msg, '405')) $code = '405 (endpoint existe!)';
                elseif (str_contains($msg, '403')) $code = '403 (accès refusé)';
                elseif (str_contains($msg, '400')) $code = '400 (bad request)';
                $io->text("  $code $method $url => " . substr($msg, 0, 200));
            }
        }
    }

    private function runTestDatedDetails(SymfonyStyle $io, string $accountId): void
    {
        $io->section("Test GET /account-details/{date} for account: $accountId");
        $config = $this->o2sClient->getConfiguration();
        $baseUrl = $config->getComptesApiUrl();

        $today = new \DateTimeImmutable();
        $dates = [];
        for ($i = 0; $i <= 7; $i++) {
            $dates[] = $today->modify("-{$i} days")->format('Y-m-d');
        }
        $dates[] = '2026-03-23';

        foreach ($dates as $date) {
            $endpoint = '/accounts/' . $accountId . '/account-details/' . $date;
            try {
                $data = $this->o2sClient->get($endpoint, [], $baseUrl);
                $refDate = $data['referenceDate'] ?? '?';
                $total = $data['totalValue'] ?? '?';
                $sitCount = count($data['situation'] ?? []);

                $io->success("$date => referenceDate=$refDate, totalValue=$total, supports=$sitCount");

                if (!empty($data['situation'])) {
                    $rows = [];
                    foreach ($data['situation'] as $s) {
                        $rows[] = [
                            $s['assetId'] ?? '?',
                            isset($s['netAssetValue']) ? number_format($s['netAssetValue'], 4, ',', ' ') : '-',
                            $s['netAssetValueDate'] ?? '-',
                            isset($s['quantity']) ? number_format($s['quantity'], 4, ',', ' ') : '-',
                            isset($s['value']) ? number_format($s['value'], 2, ',', ' ') . ' €' : '-',
                            isset($s['averagePrice']['averagePriceValue'])
                                ? number_format($s['averagePrice']['averagePriceValue'], 4, ',', ' ')
                                : '-',
                        ];
                    }
                    $io->table(['AssetId', 'NAV', 'Date VL', 'Qty', 'Value', 'PAM'], $rows);
                }
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, '404')) {
                    $io->text("  $date => 404 Not Found");
                } else {
                    $io->text("  $date => ERROR: " . substr($msg, 0, 150));
                }
            }
        }
    }

    private function maskString(string $value, int $visibleChars = 4): string
    {
        if (strlen($value) <= $visibleChars * 2) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, $visibleChars) 
            . str_repeat('*', strlen($value) - $visibleChars * 2) 
            . substr($value, -$visibleChars);
    }
}


