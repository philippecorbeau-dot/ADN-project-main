<?php

namespace App\Entity\Mail;

use App\Entity\Mail\Fields\ContactFields;
use App\Repository\Mail\ContactRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\Table(name: 'contact')]

class Contact
{
    use ContactFields;

    const SUBJECT_INVESTOR = 0;
    const SUBJECT_TECHNICAL = 2;
    const SUBJECT_OTHER = 3;
    const SUBJECT_APPOINTMENT = 4;
    const SUBJECT_COLLABORATOR = 5;
    const SUBJECT_TECHNICAL_ISSUE = 6;
    const SUBJECT_PARTNERSHIP = 7;
    const SUBJECT_QUOTATION = 8;
    const SUBJECT_DOCUMENTATION = 9;
    const SUBJECT_COMPLAINT = 10;

    const MAILTO_ADMIN = [
        'philippe.corbeau@adnfamilyoffice.fr',
    ];

    const MAILTO_BIZDEV = [
        'philippe.corbeau@adnfamilyoffice.fr',
    ];

    // Libellés clairs pour l'UI (au lieu d'afficher uniquement des chiffres)
    const SUBJECT_LIST = [
        self::SUBJECT_INVESTOR        => 'Je suis investisseur (informations / accompagnement)',
        self::SUBJECT_COLLABORATOR    => 'Je suis collaborateur',
        self::SUBJECT_PARTNERSHIP     => 'Demande de partenariat',
        self::SUBJECT_QUOTATION       => 'Demande de devis',
        self::SUBJECT_APPOINTMENT     => 'Demander un rendez-vous téléphonique',
        self::SUBJECT_DOCUMENTATION   => 'Demande de documentation',
        self::SUBJECT_TECHNICAL       => 'Question à propos du site',
        self::SUBJECT_TECHNICAL_ISSUE => 'Signaler un problème technique',
        self::SUBJECT_COMPLAINT       => 'Réclamation',
        self::SUBJECT_OTHER           => 'Autre demande',
    ];

    const ADMIN_SUBJECTS = [
        self::SUBJECT_INVESTOR,
        self::SUBJECT_TECHNICAL,
        self::SUBJECT_OTHER,
        self::SUBJECT_APPOINTMENT,
        self::SUBJECT_DOCUMENTATION,
        self::SUBJECT_COMPLAINT,
    ];

    const BIZ_DEV_SUBJECTS = [
        self::SUBJECT_COLLABORATOR,
        self::SUBJECT_PARTNERSHIP,
        self::SUBJECT_QUOTATION,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    private $id;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'lastname', type: 'string', length: 255)]
    private $lastname;

    #[ORM\Column(name: 'firstname', type: 'string', length: 255)]
    private $firstname;

    #[ORM\Column(name: 'subject', type: 'array')]
    private $subject;

    private $subject_list;

    #[ORM\Column(name: 'email', type: 'string', length: 255)]
    private $email;

    #[ORM\Column(name: 'message', type: 'text', nullable: true)]
    private $message;

    #[ORM\Column(name: 'rdv_date', type: 'datetime', nullable: true)]
    private $rdvDate;

    #[ORM\Column(name: 'phone', type: 'string', nullable: true)]
    private $phone;

    #[ORM\Column(name: 'job_function', type: 'string', length: 255, nullable: true)]
    private $function;

    #[ORM\Column(name: 'activityLocation', type: 'string', length: 255, nullable: true)]
    private $activityLocation;

    #[ORM\Column(name: 'locationBase', type: 'string', length: 255, nullable: true)]
    private $locationBase;

    #[ORM\Column(name: 'job', type: 'string', length: 255, nullable: true)]
    private $job;

    #[ORM\Column(name: 'is_read', type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $isRead = false;

    #[ORM\Column(name: 'read_at', type: 'datetime', nullable: true)]
    private ?\DateTime $readAt = null;


    public function __construct()
    {
        $this->setCreatedAt(new \DateTime('now'));
    }

    public function __toString(): string
    {
        return $this->getLastname() . ' ' . $this->getFirstname();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setLastname(?string $lastname): Contact
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setFirstname(?string $firstname): Contact
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setSubject(mixed $subject): Contact
    {
        $this->subject = $subject;

        return $this;
    }

    public function getSubject(): mixed
    {
        $subject = $this->subject;
        if (is_array($subject)) {
            return reset($subject) ?: null;
        }
        return $subject;
    }


    public function getSubjectList(): array
    {
        return self::SUBJECT_LIST;
    }

    public function getSubjectName(): ?string
    {
        $subject = $this->subject;

        if (is_array($subject)) {
            $subject = reset($subject);
        }

        if ($subject !== null && $subject !== false && isset(self::SUBJECT_LIST[$subject])) {
            return self::SUBJECT_LIST[$subject];
        }

        if (is_string($subject)) {
            return $subject;
        }

        return null;
    }

    public function setEmail(?string $email): Contact
    {
        $this->email = $email;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setMessage(string $message): Contact
    {
        $this->message = $message;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setCreatedAt(\DateTime $createdAt): Contact
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setRdvDate(\DateTime $rdvDate): Contact
    {
        $this->rdvDate = $rdvDate;

        return $this;
    }

    public function getRdvDate(): ?\DateTime
    {
        return $this->rdvDate;
    }

    public function setPhone(string $phone): Contact
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setFunction(?string $function): Contact
    {
        $this->function = $function;

        return $this;
    }

    public function getFunction(): ?string
    {
        return $this->function;
    }

    public function setActivityLocation(?string $activityLocation): Contact
    {
        $this->activityLocation = $activityLocation;

        return $this;
    }

    public function getActivityLocation(): ?string
    {
        return $this->activityLocation;
    }

    public function setLocationBase(?string $locationBase): Contact
    {
        $this->locationBase = $locationBase;

        return $this;
    }

    public function getLocationBase(): ?string
    {
        return $this->locationBase;
    }

    public function setJob(?string $job): Contact
    {
        $this->job = $job;

        return $this;
    }

    public function getJob(): ?string
    {
        return $this->job;
    }

    public function getAdnAddresses(): array
    {
        $subject = $this->getSubject();

        if (in_array($subject, self::ADMIN_SUBJECTS)) {
            return self::MAILTO_ADMIN;
        }

        if (in_array($subject, self::BIZ_DEV_SUBJECTS)) {
            return self::MAILTO_BIZDEV;
        }

        return self::MAILTO_ADMIN;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        if ($isRead && $this->readAt === null) {
            $this->readAt = new \DateTime('now');
        }
        return $this;
    }

    public function getReadAt(): ?\DateTime
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTime $readAt): self
    {
        $this->readAt = $readAt;
        return $this;
    }
}
