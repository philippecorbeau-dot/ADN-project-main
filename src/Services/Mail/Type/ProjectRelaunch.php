<?php

namespace App\Services\Mail\Type;


use App\Entity\Project\Project;
use App\Entity\User\User;

trait ProjectRelaunch
{
    public function abandonedProcess(Project $project, User $user): bool
    {
        $subject = "Vous n'avez pas encore finalisé votre investissement dans " . $project->getName();
        
        $data = [
            'project' => $project,
            'user' => $user,
        ];
        
        $templateHtml = $this->templating->render('emails/project/relaunch/abandoned-process.html.twig', $data);
        $templateText = $this->templating->render('emails/project/relaunch/abandoned-process.txt.twig', $data);

        return $this->send(
            $subject,
            [$user->getEmail()],
            [
                'html' => $templateHtml,
                'text' => $templateText,
            ]
        );
    }
}
