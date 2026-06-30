<?php

namespace App\Services\Mail\Type;

use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use App\Entity\User\User;
use App\Services\Mail\MailManager;

/**
 * TODO TO BE REWRITTEN AS CONTACTFORM
 * TODO: Test validation process and identification command
 */
class _TODELETEKycValidationResponse
{
    const SUBJECT_USER = "Votre identité n'a pu être validé";
    const SUBJECT_ADMIN = "L'identité de %firstName% %lastName% a été refusée";

    const TEMPLATE_HTML = 'emails/kyc/user-validation-refused.html.twig';
    const TEMPLATE_TEXT = 'emails/kyc/user-validation-refused.txt.twig';

    const TEMPLATE_HTML_ADMIN = 'emails/kyc/admin-validation-refused.html.twig';
    const TEMPLATE_TEXT_ADMIN = 'emails/kyc/admin-validation-refused.txt.twig';

    protected $translator;

    public function __construct(TranslatorInterface $translator, MailManager $mailManager, EngineInterface $templating)
    {
        $this->mailManager = $mailManager;
        $this->translator = $translator;
        $this->templating = $templating;
    }

    public function send(User $user, array $attachedFiles): bool
    {
        /**
         * TODO : Finish this with DocumentController
         */
        $this->mailToUser($user);
        return $this->mailToAdmin($user, $attachedFiles);
    }

    protected function mailToUser(User $user): bool
    {
        $data['title'] = $this->getSubject(self::SUBJECT_USER);
        $text = $this->getTemplate(self::TEMPLATE_TEXT, $data);
        $html = $this->getTemplate(self::TEMPLATE_HTML, $data);

        return $this->mailManager->send(
            $this->getSubject(self::SUBJECT_USER),
            MailManager::ADMIN_ADDRESS, // $user->getEmail()
            [
                'text' => $text,
                'html' => $html,
            ]
        );
    }

    protected function mailToAdmin(User $user, array $attachedFiles): bool
    {
        $adminData = [
            '%firstname%' => $user->getFirstname(),
            '%lastname%' => $user->getLastName(),
            'title' => $this->getSubject(self::SUBJECT_ADMIN),
            'user' => $user
        ];

        $text = $this->getTemplate(self::TEMPLATE_TEXT_ADMIN, $adminData);
        $html = $this->getTemplate(self::TEMPLATE_HTML_ADMIN, $adminData);

        return $this->mailManager->send(
            $this->getSubject(self::SUBJECT_ADMIN, $adminData),
            MailManager::ADMIN_ADDRESS,
            [
                'text' => $text,
                'html' => $html,
            ]
        );
    }

    public function getTemplate(string $path, array $data): string
    {
        return $this->templating->render($path, $data);
    }

    public function getSubject(string $subject, array $data = []): string
    {
        return $this->translator->trans($subject, $data);
    }
}