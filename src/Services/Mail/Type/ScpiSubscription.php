<?php

namespace App\Services\Mail\Type;

trait ScpiSubscription
{
    /**
     * Cette méthode envoie un mail à l'utilisateur ayant réalisé un tunnel de souscription SCPI.
     * Il est envoyé une fois la dernière étape du tunnel atteinte.
     * Ce mail l'invite à prendre un RDV Calendly avec un conseiller pour terminer sa souscription.
     * @param array $data
     * @return bool
     */
    public function scpiCallClient(array $data): bool
    {
        $subject = "Finalisez votre souscription SCPI";
        $templateHtml = $this->templating->render('emails/scpi/call-client.html.twig', $data);
        $templateText = $this->templating->render('emails/scpi/call-client.txt.twig', $data);

        return $this->send(
            $subject,
            [$data['investment']->getUser()->getEmail()],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }

    /**
     * Cette méthode envoie un mail à l'utilisateur pour l'inviter à signer son bulletin de souscription SCPI.
     * Il est envoyé une fois la dernière étape du tunnel atteinte.
     * Ce mail l'invite à prendre un RDV Calendly avec un conseiller pour terminer sa souscription.
     * @param array $data
     * @return bool
     */
    public function scpiSignature(array $data): bool
    {
        $subject = "Signez votre souscription SCPI";
        $templateHtml = $this->templating->render('emails/scpi/signature.html.twig', $data);
        $templateText = $this->templating->render('emails/scpi/signature.txt.twig', $data);

        return $this->send(
            $subject,
            [$data['investment']->getUser()->getEmail()],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }

    /**
     * Cette méthode envoie un mail à l'utilisateur pour lui dire de payer le montant de sa souscription SCPI.
     * Il est envoyé une fois le bulletin de souscription signé.
     * Ce mail lui envoie le rib de la SCPI pour payer sa souscription.
     * @param array $data
     * @return bool
     */
    public function scpiPaiement(array $data): bool
    {
        $subject = "Finsalisez votre investissement";
        $templateHtml = $this->templating->render('emails/scpi/paiement.html.twig', $data);
        $templateText = $this->templating->render('emails/scpi/paiement.txt.twig', $data);

        return $this->send(
            $subject,
            [$data['investment']->getUser()->getEmail()],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
