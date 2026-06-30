<?php

namespace App\Entity\Mail;

use App\Entity\Mail\Fields\MailFields;
use App\Repository\Mail\MailRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User\User;


#[ORM\Table(name: 'mail')]
#[ORM\Entity(repositoryClass: MailRepository::class)]

class Mail
{
    use MailFields;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    private $name;

    #[ORM\Column(name: 'subject', type: 'string', length: 255)]
    private $subject;

    #[ORM\Column(name: 'sended_to', type: 'string', length: 255)]
    private $sentTo;

    #[ORM\Column(name: 'receiver_ip', type: 'string', length: 20)]
    private $recipientIp;

    #[ORM\Column(name: 'createdAt', type: 'datetime')]
    private $createdAt;

    #[ORM\Column(name: 'templateHtml', type : 'text')]
    private $templateHtml;

    #[ORM\Column(name: 'templateTxt', type: 'text')]
    private $templateTxt;

    #[ORM\Column(name: 'token', type: 'string', length: 50, unique: true)]
    private $token;

    #[ORM\ManyToOne(targetEntity: 'App\Entity\User\User', inversedBy: 'mails')]
    private $user;

    public function __construct()
    {
        $this->createdAt = new \DateTime('now');
    }

    public function __toString(): string
    {
        return $this->getName();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setName(?string $name): Mail
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setCreatedAt(\DateTime $createdAt): Mail
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setTemplateHtml(string $templateHtml): Mail
    {
        $this->templateHtml = $templateHtml;

        return $this;
    }

    public function getTemplateHtml(): ?string
    {
        return $this->templateHtml;
    }

    public function setTemplateTxt(string $templateTxt): Mail
    {
        $this->templateTxt = $templateTxt;

        return $this;
    }

    public function getTemplateTxt(): ?string
    {
        return $this->templateTxt;
    }

    public function setUser(User $user = null): Mail
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setSubject(string $subject): Mail
    {
        $this->subject = $subject;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setToken(string $token): Mail
    {
        $this->token = $token;

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getSentTo(): string
    {
        return $this->sentTo;
    }

    public function setSentTo(string $sentTo): Mail
    {
        $this->sentTo = $sentTo;

        return $this;
    }

    public function getRecipientIp(): ?string
    {
        return $this->recipientIp;
    }

    public function setRecipientIp(string $recipientIp): Mail
    {
        $this->recipientIp = $recipientIp;

        return $this;
    }
}
