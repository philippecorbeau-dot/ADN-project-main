<?php

namespace App\Services\Mail\Type;

use App\Entity\Investment\Investment;
use App\Entity\Investment\Refund;
use App\Entity\Project\Progress;
use App\Entity\Project\Project;
use App\Entity\User\User;

/**
 * Reset password mailer for users
 */
trait ProjectReport
{
    public function projectReport(array $investors, array $data): bool
    {
        $subject = "Rapport du projet " . $data['progress']->getProject()->getName();

        foreach ($investors as $investor) {
            $templateHtml = $this->replaceTemplateVariables(
                $this->templating->render(
                    'emails/project/report.html.twig',
                    $data + $investor
                ),
                $investor['user'],
                $data['progress'],
                $data['investment'],
                $data['refund']
            );
            $templateText = $this->replaceTemplateVariables(
                $this->templating->render(
                    'emails/project/report.txt.twig',
                    $data + $investor
                ),
                $investor['user'],
                $data['progress'],
                $data['investment'],
                $data['refund']
            );

            $this->send(
                $subject,
                [$investor['email']],
                [
                    'html' => $templateHtml,
                    'text' => $templateText,
                ]
            );
        }

        return true;
    }

    public function replaceTemplateVariables($template, User $user, Progress $progress, Investment $investment = null, Refund $refund = null)
    {
        $variables = [
            '{Firstname}',
            '{Lastname}',
            '{InterestRate}',
            '{ProjectName}'
        ];
        $replacedVariables = [
            $user->getFirstName(),
            $user->getLastName(),
            number_format($progress->getProject()->getYieldPercent(), 2, ',',' '),
            $progress->getProject()->getName(),
        ];

        if (null !== $investment) {
            $variables[] = '{InvestmentAmount}';
            $replacedVariables[] = number_format($investment->getTotalPrice(), 0, ',', ' ');
        }

        if (null !== $refund) {
            array_push($variables,
                '{NumberOfDays}',
                '{RefundAmount}',
                '{TotalInterestRate}',
                '{Taxation}'
            );
            array_push($replacedVariables,
                number_format($refund->getDays(),0,',',' '),
                number_format($refund->getAmount(),0,',',' '),
                number_format(($progress->getProject()->getYieldPercent()) * ($refund->getDays() / 365), 2, ',',' '),
                number_format($refund->getTaxationAmount(),0,',',' ')
            );
        }

        return str_replace($variables, $replacedVariables, $template);
    }
}
