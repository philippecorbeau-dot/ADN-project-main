<?php

namespace App\Command;

use App\Service\MarketData\TwelveDataClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:probe-twelvedata',
    description: 'Effectue des requêtes brutes Twelve Data et affiche le JSON (diagnostic).',
)]
class ProbeTwelveDataCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly TwelveDataClient $twelve,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('q', InputArgument::REQUIRED, 'ISIN/symbole Twelve Data (ex: FR0000987950 ou 0P00000NYV)')
            ->addOption('endpoint', null, InputOption::VALUE_REQUIRED, 'symbol_search|time_series', 'symbol_search')
            ->addOption('exchange', null, InputOption::VALUE_REQUIRED, 'Exchange (optionnel)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $q = (string) $input->getArgument('q');
        $endpoint = (string) $input->getOption('endpoint');
        $exchange = $input->getOption('exchange');
        $apiKey = (new \ReflectionClass($this->twelve))->getMethod('hasUsableApiKey')->invoke($this->twelve) ? 'ok' : 'missing';

        $io->writeln("API key: $apiKey");

        $params = ['apikey' => (new \ReflectionClass($this->twelve))->getMethod('hasUsableApiKey')->invoke($this->twelve) ? 'hidden' : 'none'];

        try {
            $rm = new \ReflectionMethod($this->twelve, 'getApiKey');
            $rm->setAccessible(true);
            $apiKeyVal = (string) ($rm->invoke($this->twelve) ?? '');
            if ($endpoint === 'symbol_search') {
                $params = [
                    'symbol' => $q,
                    'outputsize' => 50,
                    'apikey' => 'REDACTED',
                ];
                $resp = $this->http->request('GET', 'https://api.twelvedata.com/symbol_search', [
                    'query' => array_filter([
                        'apikey' => $apiKeyVal ?: null,
                        'symbol' => $q,
                        'outputsize' => 50,
                        'exchange' => $exchange ?: null,
                    ]),
                    'timeout' => 12,
                ]);
            } else {
                $resp = $this->http->request('GET', 'https://api.twelvedata.com/time_series', [
                    'query' => array_filter([
                        'apikey' => $apiKeyVal ?: null,
                        'symbol' => $q,
                        'interval' => '1day',
                        'outputsize' => 'compact',
                        'exchange' => $exchange ?: null,
                    ]),
                    'timeout' => 12,
                ]);
            }
            $status = $resp->getStatusCode();
            $json = $resp->getContent(false);
            $io->writeln("HTTP $status");
            $io->writeln($json);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}


