<?php

namespace App\Services\Mail\Type;

/**
 * Sends mail from App\Command\User\KycStatusCommand
 * Reports the statuses of KYC's
 */
trait KycStatusReport
{
    public function kycStatusReport(array $to, array $data): bool
    {
        $subject = '[KYC Reporting] Du '.(new \DateTime())->format('d-m-Y G:i:s');
        $templateHtml = $this->templating->render('emails/kyc-status/report.html.twig');
        $templateText = $this->templating->render('emails/kyc-status/report.txt.twig');

        return $this->send(
            $subject,
            $to,
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
