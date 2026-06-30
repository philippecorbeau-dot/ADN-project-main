<?php

namespace App\Command;

use App\Entity\User\User;
use App\Services\User\InvestorProfileScorer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:inspect-user-kyc', description: 'Affiche les données KYC (Step2/3/4) et le score de profil pour un utilisateur par email')]
class InspectUserKycCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvestorProfileScorer $scorer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email utilisateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $output->writeln('<error>Utilisateur introuvable: ' . $email . '</error>');
            return Command::FAILURE;
        }

        $info = $user->getInfo();
        $pro = $user->getPro();

        $output->writeln('== USER ==');
        $output->writeln('ID: ' . $user->getId());
        $output->writeln('Email: ' . $user->getEmail());
        $output->writeln('Type: ' . ($user->isPro() ? 'PRO' : 'PARTICULIER'));

        if ($info) {
            $output->writeln("== INFO (Step2/3/4) ==");
            $dump = [
                'objective' => $info->getObjective(),
                'investmentTerm' => $info->getInvestmentTerm(),
                'liquidity' => $info->getLiquidity(),
                'sourceOfFunds' => $info->getSourceOfFunds(),
                'depositSavings_total' => $info->getDepositSavings(),
                'depositSavings_breakdown' => [
                    'checking' => $info->getDepositSavingsChecking(),
                    'livretA' => $info->getDepositSavingsLivretA(),
                    'ldd' => $info->getDepositSavingsLdd(),
                    'csl' => $info->getDepositSavingsCsl(),
                    'other' => $info->getDepositSavingsOther(),
                ],
                'salary' => $info->getSalary(),
                'accountSecurities' => $info->getAccountSecurities(),
                'capitalisation' => $info->getCapitalisation(),
                'scpi' => $info->getScpi(),
                'realestateIncome' => $info->getRealestateIncome(),
                'realestate_total' => $info->getRealestate(),
                'realestate_breakdown' => [
                    'primaryResidence' => $info->getRealestatePrimaryResidence(),
                    'investment' => $info->getRealestateInvestment(),
                ],
                'rent' => $info->getRent(),
                'mif' => $info->isMif(),
                'attestations' => [
                    'attestMif' => $info->getAttestMif(),
                    'attestAware' => $info->isAttestAware(),
                    'attestTruth' => $info->isAttestTruth(),
                ],
            ];
            $output->writeln(json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if ($pro) {
            $output->writeln("== PRO (Step3 PRO) ==");
            $dump = [
                'capital' => $pro->getCapital(),
                'turnover' => $pro->getTurnover(),
                'oldResult' => $pro->getOldResult(),
                'forecastTurnover' => $pro->getForecastTurnover(),
                'stocks' => $pro->getStocks(),
            ];
            $output->writeln(json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        // Calcul du score et affichage
        $this->scorer->calculateAndUpdateProfile($user);
        $this->em->persist($user);
        $this->em->flush();

        $output->writeln('== SCORE ==');
        $output->writeln('Profil: ' . $user->getInvestorProfile());
        $output->writeln('Score: ' . $user->getInvestorScore());

        return Command::SUCCESS;
    }
}


