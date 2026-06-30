<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

#[AsCommand(
    name: 'app:test-mailer',
    description: 'Envoie un mail de test via Mailer',
)]

class testMailerCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%app.mail.sender_email%')]
        private readonly string $senderEmail,
        #[Autowire('%app.mail.sender_name%')]
        private readonly string $senderName,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to($this->senderEmail)
            ->subject('Test Symfony Mailer')
            ->htmlTemplate('emails/test.html.twig')
            ->context(['userName' => 'Éric']);

        $this->mailer->send($email);
        $output->writeln('✅ Email envoyé ! Vérifie ta boîte Mailjet ou ta boîte de réception.');
        $output->writeln(sprintf('📧 Expéditeur: %s (%s)', $this->senderEmail, $this->senderName));

        return Command::SUCCESS;
    }
}
