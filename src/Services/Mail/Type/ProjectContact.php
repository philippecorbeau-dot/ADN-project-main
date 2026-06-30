<?php

namespace App\Services\Mail\Type;

use Symfony\Component\HttpFoundation\Request;
use App\Entity\Project\Project;
use App\Entity\Mail\Contact;

trait ProjectContact
{
    public function projectContactToHomunity(Request $request, Project $project): bool
    {
        $subject = $this->translator->trans("[". $project->getName() ."] Demande d'information");
        $templateHtml = $this->templating->render('emails/project-contact/homunity.html.twig', [
            'subject' => $subject,
            'project' => $project,
            'form' => $request->request->all(),
        ]);
        $templateText = $this->templating->render('emails/project-contact/homunity.txt.twig', [
            'subject' => $subject,
            'project' => $project,
            'form' => $request->request->all(),
        ]);

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
