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

#[AsCommand(
    name: 'app:test-time-series',
    description: 'Teste TwelveDataClient::getTimeSeries pour un symbole donné.',
)]
class TestTimeSeriesCommand extends Command
{
    public function __construct(private readonly TwelveDataClient $td)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('symbol', InputArgument::REQUIRED, 'Symbole Twelve Data (ex: 0P00000NYV)')
            ->addOption('exchange', null, InputOption::VALUE_REQUIRED, 'Exchange (ex: FSX)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $symbol = (string) $input->getArgument('symbol');
        $exchange = $input->getOption('exchange') ? (string) $input->getOption('exchange') : null;
        try {
            $rm = new \ReflectionMethod($this->td, 'getApiKey');
            $rm->setAccessible(true);
            $k = (string) ($rm->invoke($this->td) ?? '');
            $io->writeln('apiKey=' . ($k !== '' ? substr($k, 0, 6) . '…' : 'EMPTY'));
        } catch (\Throwable $e) {
            $io->writeln('apiKey=?');
        }
        $values = $this->td->getTimeSeries($symbol, '1day', '1', $exchange);
        if (empty($values)) {
            $io->warning('values empty; lastError=' . (string) ($this->td->getLastError() ?? 'null'));
            return Command::SUCCESS;
        }
        $first = $values[0];
        $io->writeln(sprintf('first close=%s date=%s', (string)($first['close'] ?? ''), (string)($first['datetime'] ?? '')));
        return Command::SUCCESS;
    }
}


