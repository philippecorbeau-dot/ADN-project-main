<?php

namespace App\Services\Mail\Type;

use App\Entity\Mail\Contact;

/**
 * Convention : to avoid overriding other traits functions, methods of the trait starts by the trait name
 */
trait ContactForm
{
    public function contactFormSend(Contact $contact): bool
    {
        $this->contactFormToHomunity($contact);
        return $this->contactFormToUser($contact);
    }

    public function contactFormToUser(Contact $contact): bool
    {
        $sendTo = [$contact->getEmail()];
        $subject = $this->translator->trans("Merci pour votre prise de contact");
        $templateHtml = $this->templating->render('emails/contact-form/contact.html.twig', ['subject' => $subject]);
        $templateText = $this->templating->render('emails/contact-form/contact.txt.twig');

        return $this->send(
            $subject,
            $sendTo,
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }

    public function contactFormToHomunity(Contact $contact): bool
    {
        $subject = $this->translator->trans("Quelqu'un vient de vous contacter");
        $templateHtml = $this->templating->render('emails/contact-form/contact-homunity.html.twig', [
            'subject' => $subject,
            'contact' => $contact
        ]);
        $templateText = $this->templating->render('emails/contact-form/contact-homunity.txt.twig', ['contact' => $contact]);

        return $this->send(
            $subject,
            $this->getAdminTeamAddress(),
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
