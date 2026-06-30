<?php

namespace App\Entity\User;

use App\Entity\Blog\Post;
use App\Entity\Comment\Comment;
use App\Entity\Mail\Mail;
use App\Entity\User\User\Fields;
use App\Entity\User\Knowledge\InvestorKnowledge;
use App\Repository\User\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\PersistentCollection;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users_adn')]
#[ORM\Index(name: 'idx_users_o2s_contact_id', columns: ['o2s_contact_id'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: "L'adresse e-mail est déjà utilisée.")]
#[Vich\Uploadable]
#[\AllowDynamicProperties]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: true)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use Fields;
    use SoftDeleteableEntity;
    use SoftDeleteableEntity;

    const FRANCE_CODE = 'FR';

    const PIPELINE_STAGE_ID = 46;

    const USER_TYPE_PRIVATE = 100;
    const USER_TYPE_PRO = 200;
    const USER_TYPE_CGP = 300;

    // Rôles utilisateurs clients
    const ROLE_USER        = 'ROLE_USER';
    const ROLE_KYC_OUTDATED = 'ROLE_KYC_OUTDATED';
    const ROLE_USER_IDENTIFIED  = 'ROLE_USER_IDENTIFIED';
    const ROLE_USER_BLOCKED = 'ROLE_USER_BLOCKED';
    const ROLE_PROFILE_AWARE = 'ROLE_PROFILE_AWARE';
    const ROLE_PROFILE_BEGINNER = 'ROLE_PROFILE_BEGINNER';

    // Rôles administratifs du back-office (hiérarchie : SUPER_ADMIN > ADMIN > autres)
    const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';           // Accès total + gestion des rôles
    const ROLE_ADMIN = 'ROLE_ADMIN';                       // Accès back-office complet (sans gestion rôles)
    const ROLE_ADMIN_OPERATOR = 'ROLE_ADMIN_OPERATOR';     // Opérateur - accès lecture/actions limitées
    const ROLE_ADMIN_KYC = 'ROLE_ADMIN_KYC';               // Gestionnaire KYC - validation documents
    const ROLE_ADMIN_SUPPORT = 'ROLE_ADMIN_SUPPORT';       // Support client - chat et messages
    const ROLE_ADMIN_MARKETING = 'ROLE_ADMIN_MARKETING';   // Marketing - actualités, contenu
    const GENDER_MAN = 'MAN';
    const GENDER_WOMAN = 'WOMAN';

    const BIRTH_DEPARTMENT_FOREIGNER = '99';

    const STEP_KYC_PROFILE = 1;
    const STEP_KYC_OBJECTIVES = 2;
    const STEP_KYC_PATRIMONY = 3;
    const STEP_KYC_EXPERIENCE = 4;
    const STEP_KYC_DOCUMENTS = 5;

    const INTERESTED_BY = [
        'Crowdfunding immobilier',
        'Investissement locatif',
        'SCPI',
        'Je ne sais pas encore'
    ];

    const USER_TYPE_VALUES = [
        self::USER_TYPE_PRIVATE => 'Private',
        self::USER_TYPE_PRO => 'Pro',
        self::USER_TYPE_CGP => 'CGP',
    ];

    // Tous les rôles disponibles
    const ROLES_LIST = [
        self::ROLE_USER => self::ROLE_USER,
        self::ROLE_USER_IDENTIFIED => self::ROLE_USER_IDENTIFIED,
        self::ROLE_KYC_OUTDATED => self::ROLE_KYC_OUTDATED,
        self::ROLE_USER_BLOCKED => self::ROLE_USER_BLOCKED,
        self::ROLE_PROFILE_BEGINNER => self::ROLE_PROFILE_BEGINNER,
        self::ROLE_PROFILE_AWARE => self::ROLE_PROFILE_AWARE,
        self::ROLE_SUPER_ADMIN => self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN => self::ROLE_ADMIN,
        self::ROLE_ADMIN_OPERATOR => self::ROLE_ADMIN_OPERATOR,
        self::ROLE_ADMIN_KYC => self::ROLE_ADMIN_KYC,
        self::ROLE_ADMIN_SUPPORT => self::ROLE_ADMIN_SUPPORT,
        self::ROLE_ADMIN_MARKETING => self::ROLE_ADMIN_MARKETING,
    ];

    // Rôles permettant l'accès au back-office
    const ROLES_ADMIN_LIST = [
        self::ROLE_SUPER_ADMIN => self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN => self::ROLE_ADMIN,
        self::ROLE_ADMIN_OPERATOR => self::ROLE_ADMIN_OPERATOR,
        self::ROLE_ADMIN_KYC => self::ROLE_ADMIN_KYC,
        self::ROLE_ADMIN_SUPPORT => self::ROLE_ADMIN_SUPPORT,
        self::ROLE_ADMIN_MARKETING => self::ROLE_ADMIN_MARKETING,
    ];

    // Labels lisibles pour les rôles admin
    const ROLES_ADMIN_LABELS = [
        self::ROLE_SUPER_ADMIN => 'Super Admin',
        self::ROLE_ADMIN => 'Administrateur',
        self::ROLE_ADMIN_OPERATOR => 'Opérateur',
        self::ROLE_ADMIN_KYC => 'Gestionnaire KYC',
        self::ROLE_ADMIN_SUPPORT => 'Support Client',
        self::ROLE_ADMIN_MARKETING => 'Marketing',
    ];

    // Permissions par rôle (modules accessibles)
    const ROLES_PERMISSIONS = [
        self::ROLE_SUPER_ADMIN => ['*'], // Accès total
        self::ROLE_ADMIN => ['dashboard', 'users', 'kyc', 'messages', 'chat', 'markets', 'products', 'news', 'contacts', 'activities', 'pros', 'portfolio', 'comparison', 'opportunity_documents'],
        self::ROLE_ADMIN_OPERATOR => ['dashboard', 'users', 'kyc', 'messages', 'contacts'],
        self::ROLE_ADMIN_KYC => ['dashboard', 'users', 'kyc', 'pros'],
        self::ROLE_ADMIN_SUPPORT => ['dashboard', 'users', 'messages', 'chat', 'contacts', 'activities'],
        self::ROLE_ADMIN_MARKETING => ['dashboard', 'news', 'markets', 'products', 'comparison', 'opportunity_documents'],
    ];

    // Couleurs pour les badges de rôles
    const ROLE_COLORS_LIST = [
        self::ROLE_SUPER_ADMIN => 'purple',
        self::ROLE_ADMIN => 'indigo',
        self::ROLE_ADMIN_OPERATOR => 'blue',
        self::ROLE_ADMIN_KYC => 'green',
        self::ROLE_ADMIN_SUPPORT => 'cyan',
        self::ROLE_ADMIN_MARKETING => 'orange',
        self::ROLE_USER_BLOCKED => 'red',
    ];

    // statuts maritaux
    const MARITAL_STATUS_LIST = [
        'Célibataire',
        'Divorcé(e)',
        'Marié(e) avec contrat de mariage',
        'Marié(e) sans contrat de mariage',
        'Pacsé(e) avec contrat',
        'Pacsé(e) sans contrat',
        'Veuf(ve)',
        'Marié(e) avec contrat de mariage (communauté universelle)',
        'Marié(e) avec contrat de mariage (communauté réduite aux acquêts) ',
        'Marié(e) avec contrat de mariage (régime séparatiste)',
        'Séparé(e)',
        'Union autre',
    ];

    // professions
    const PROFESSION_LIST = [
        0 => 'Agriculteur exploitant',
        1 => 'Artisan',
        2 => "Commerçant et chef d'entreprise",
        3 => 'Cadre',
        4 => 'Professeur',
        5 => 'Profession libérale',
        6 => 'Profession scientifique ou artistique',
        7 => 'Profession intermediaire',
        8 => 'Employé',
        9 => 'Ouvrier',
        10 => 'Retraité',
        12 => 'Chômeurs',
        13 => 'Elèves, Etudiants, Apprentis',
        11 => 'Autre personne sans activité professionnelle',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id;

    #[Assert\Email(
        message: "{{ value }} n'est pas un email valide",
    )]
    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private ?string $email;

    #[Assert\Length(
        min: 12,
        minMessage: "Le mot de passe doit comporter au moins {{ limit }} caractères"
    )]
    #[ORM\Column(type: 'string', length: 255)]
    private ?string $password;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $username;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $usernameCanonical;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $emailCanonical;

    #[Assert\Length(
        min: 12,
        minMessage: "Le mot de passe doit comporter au moins {{ limit }} caractères",
        groups: ['resetPassword']
    )]
    private ?string $plainPassword = null;

    #[ORM\Column(name: 'last_name', type: 'string', length: 45, nullable: true)]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le nom est trop court.', maxMessage: 'Le nom est trop long.')]
    #[Assert\NotBlank(message: 'Merci de remplir ce champ', groups: ['profile', 'invest'])]
    #[Assert\Regex(pattern: '#[<>]#', message: 'Les caractères spéciaux sont interdits', match: false, groups: ['profile', 'invest'])]
    private $lastName;

    #[ORM\Column(name: 'birth_last_name', type: 'string', length: 45, nullable: true)]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le nom est trop court.', maxMessage: 'Le nom est trop long.')]
    private $birthLastName;

    #[ORM\Column(name: 'first_name', type: 'string', length: 45, nullable: true)]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le prénom est trop court.', maxMessage: 'Le prénom est trop long.')]
    #[Assert\NotBlank(message: 'Merci de remplir ce champ', groups: ['profile', 'invest'])]
    #[Assert\Regex(pattern: '#[<>]#', message: 'Les caractères spéciaux sont interdits', match: false, groups: ['profile', 'invest'])]
    private $firstName;

    #[ORM\Column(name: 'birth_first_name', type: 'string', length: 45, nullable: true)]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le nom est trop court.', maxMessage: 'Le nom est trop long.')]
    private $birthFirstName;

    #[ORM\Column(name: 'roles', type: 'json', nullable: true)]
    #[Assert\Type(type: 'array')]
    private ?array $roles = [];

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'reset_token', type: 'string', length: 100, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(name: 'reset_token_expires_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resetTokenExpiresAt = null;

    #[Assert\Type(\DateTimeImmutable::class)]
    #[Assert\NotBlank(
        message: "Merci d'indiquer votre date d'anniversaire",
        groups: ['profile', 'invest']
    )]
    #[ORM\Column(name: 'birthday', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $birthday = null;

    #[ORM\Column(name: 'birthplace', type: 'string', nullable: true)]
    #[Assert\NotBlank(message: 'Merci de remplir ce champ', groups: ['profile', 'invest'])]
    private $birthplace;

    #[ORM\Column(name: 'postal_code_birthplace', type: 'string', length: 10, nullable: true)]
    private $postalCodeBirthplace;

    #[ORM\Column(name: 'insee_code', type: 'string', length: 10, nullable: true)]
    private $inseeCode;

    #[ORM\Column(name: 'insee_code_birthplace', type: 'string', length: 10, nullable: true)]
    private $inseeCodeBirthplace;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre nationalité', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'nationality', type: 'string', nullable: true)]
    private $nationality;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre numéro de téléphone', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'phone', type: 'string', length: 15, nullable: true)]
    private $phone;

    #[ORM\Column(name: 'address', type: 'string', length: 255, nullable: true)]
    private $address;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre adresse', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'address_line1', type: 'string', length: 255, nullable: true)]
    private $addressLine1;

    #[ORM\Column(name: 'address_line2', type: 'string', length: 255, nullable: true)]
    private $addressLine2;

    #[ORM\Column(name: 'tax_address', type: 'string', length: 255, nullable: true)]
    private $taxAddress;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre ville', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'city', type: 'string', length: 155, nullable: true)]
    private $city;

    #[ORM\Column(name: 'region', type: 'string', length: 155, nullable: true)]
    private $region;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre Code postal', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'postal_code', type: 'string', length: 10, nullable: true)]
    private $postalCode;

    #[Assert\NotBlank(message: 'Merci d\'indiquer votre Pays', groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'country', type: 'string', length: 2, nullable: true)]
    private $country;

    #[Vich\UploadableField(mapping: 'user_image', fileNameProperty: 'imageName')]
    #[Assert\File(
        maxSize: '8192k',
        mimeTypes: ['image/png', 'image/jpeg'],
        maxSizeMessage: "L'image ne doit pas peser plus de 8Mo",
        mimeTypesMessage: "Merci d'envoyer des images au format .JPG ou .PNG"
    )]
    private $imageFile;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $imageName;

    #[Assert\NotBlank(message: "Merci de choisir une option", groups: ['profile', 'invest'])]
    #[Assert\Choice(callback: 'getGenders')]
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $gender;

    #[Assert\NotBlank(message: "Merci d'indiquer votre statut", groups: ['profile', 'invest'])]
    #[ORM\Column(name: 'marital_status', type: 'integer', nullable: true)]
    private $maritalStatus;

    #[ORM\Column(name: 'profession', type: 'integer', nullable: true)]
    private $profession;

    #[ORM\Column(name: 'facebook_id', type: 'string', length: 255, nullable: true)]
    private $facebook_id;

    #[ORM\Column(name: 'facebook_access_token', type: 'string', length: 255, nullable: true)]
    private $facebook_access_token;

    #[ORM\Column(name: 'google_id', type: 'string', length: 255, nullable: true)]
    private $googleId;

    #[ORM\Column(name: 'google_access_token', type: 'string', length: 255, nullable: true)]
    private $google_access_token;

    #[ORM\Column(name: 'linkedin_id', type: 'string', length: 255, nullable: true)]
    private $linkedin_id;

    #[ORM\Column(name: 'linkedin_access_token', type: 'string', length: 255, nullable: true)]
    private $linkedin_access_token;

    #[ORM\OneToOne(targetEntity: Want::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private $wants;

    #[ORM\OneToOne(targetEntity: Info::class, inversedBy: 'user', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private $info;

    #[ORM\OneToOne(targetEntity: Document::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private $document;

    #[ORM\OneToMany(targetEntity: KycDocument::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private $kycDocuments;

    #[ORM\OneToMany(targetEntity: 'App\\Entity\\Blog\\Post', mappedBy: 'user', cascade: ['persist'])]
    private $posts;

    #[ORM\Column(name: 'type', type: 'integer', nullable: true)]
    private $type = self::USER_TYPE_PRIVATE;

    #[ORM\OneToOne(targetEntity: Pro::class, inversedBy: 'user', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private $pro;

    #[ORM\Column(name: 'retargeted', type: 'boolean', nullable: true)]
    private $retargeted;

    #[ORM\OneToMany(targetEntity: 'App\\Entity\\Mail\\Mail', mappedBy: 'user', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private $mails;

    #[ORM\Column(name: 'sponsorship', type: 'boolean')]
    private $sponsorship = true;

    #[ORM\Column(name: 'pipedrive_id', type: 'integer', nullable: true)]
    private $pipedrive_id;

    #[ORM\Column(name: 'ip', type: 'string', length: 45, nullable: true)]
    private $ip;

    #[ORM\Column(name: 'source', type: 'string', length: 255, nullable: true)]
    private $source;

    #[ORM\Column(name: 'how_known', type: 'string', length: 255, nullable: true)]
    private $howKnown;

    #[Assert\IsTrue(message: "Vous devez accepter pour vous inscrire")]
    #[ORM\Column(name: 'risk1', type: 'boolean', nullable: true)]
    private $risk1;

    #[Assert\IsTrue(message: "Vous devez accepter pour vous inscrire")]
    #[ORM\Column(name: 'risk2', type: 'boolean', nullable: true)]
    private $risk2;

    #[ORM\Column(name: 'step_registration_token', type: 'string', unique: true, nullable: true)]
    private $stepRegistrationToken;

    #[ORM\Column(name: 'birth_department', type: 'string', length: 10, nullable: true)]
    private $birthDepartment;

    #[ORM\Column(name: 'birth_country', type: 'string', length: 31, nullable: true)]
    private $birthCountry;

    #[ORM\OneToMany(targetEntity: 'App\\Entity\\Comment\\Comment', mappedBy: 'user')]
    private $comments;

    #[ORM\OneToOne(targetEntity: Control::class, mappedBy: 'user')]
    private $control;

    #[ORM\OneToOne(targetEntity: Cgp::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private $cgp;

    #[ORM\Column(name: 'cosigner', type: 'boolean', nullable: true)]
    private $cosigner;

    #[ORM\OneToOne(targetEntity: Mailing::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private $mailing;

    #[Assert\Count(min: 1, max: 3, minMessage: "Vous devez renseigner au moins un choix", groups: ['createprofile'])]
    #[Assert\Type(type: 'array')]
    #[ORM\Column(name: 'interested_by', nullable: true)]
    private ?array $interestedBy;

    #[ORM\OneToOne(targetEntity: Config::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private $config;

    // Marketing supprimé (entité et relation)
    // #[ORM\OneToOne(targetEntity: 'App\Entity\User\Marketing', cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    // #[ORM\JoinColumn(onDelete: 'CASCADE')]
    // private $marketing;

    #[ORM\OneToOne(targetEntity: AdditionalInfo::class, mappedBy: 'user')]
    private $additionalInfo = null;

    #[ORM\Column(name: 'is_aware_profile', type: 'boolean', nullable: true)]
    private $isAwareProfile;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private $description;

    #[ORM\Column(name: 'step_kyc', type: 'smallint', nullable: true, options: ['unsigned' => true])]
    private $stepKyc;

    #[Assert\NotBlank(message: "Merci d'indiquer votre Résidence fiscale", groups: ['profile'])]
    #[ORM\Column(name: 'tax_residence', type: 'string', length: 2, nullable: true)]
    private $taxResidence;

    private bool $preventAutoUpdate = false;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $adminDarkMode = false;

    #[ORM\Column(name: 'suspended_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $suspendedAt = null;

    #[ORM\Column(name: 'suspended_reason', type: 'string', length: 500, nullable: true)]
    private ?string $suspendedReason = null;

    #[ORM\Column(name: 'redirect_to_moneypitch', type: 'boolean', options: ['default' => true])]
    private bool $redirectToMoneyPitch = true;

    // ==================== O2S INTEGRATION ====================

    #[ORM\Column(name: 'o2s_contact_id', type: 'string', length: 100, nullable: true)]
    private ?string $o2sContactId = null;

    #[ORM\Column(name: 'o2s_synced_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $o2sSyncedAt = null;

    #[ORM\Column(name: 'o2s_type_contact', type: 'string', length: 20, nullable: true)]
    private ?string $o2sTypeContact = null;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->roles = [];
        $this->posts = [];
        $this->mails = [];
        $this->kycDocuments = new ArrayCollection();
        $this->investorKnowledge = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * TODO review all getters/setters
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->firstName.' '.$this->lastName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): User
    {
        $this->lastName = $lastName;

        return $this;
    }


    public function getBirthLastName(): ?string
    {
        return $this->birthLastName;
    }

    public function setBirthLastName(?string $birthLastName): User
    {
        $this->birthLastName = $birthLastName;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): User
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getBirthFirstName(): ?string
    {
        return $this->birthFirstName;
    }

    public function setBirthFirstName(?string $birthFirstName): User
    {
        $this->birthFirstName = $birthFirstName;

        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstName.' '.$this->lastName;
    }

    /**
     * Get message = "{{ value }} n'est pas un email valide",
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail($email): void
    {
        $this->email = $email;
        $this->username = $email;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getUsernameCanonical(): ?string
    {
        return $this->usernameCanonical;
    }

    public function setUsernameCanonical(?string $usernameCanonical): void
    {
        $this->usernameCanonical = $usernameCanonical;
    }

    public function getEmailCanonical(): ?string
    {
        return $this->emailCanonical;
    }

    public function setEmailCanonical($emailCanonical): void
    {
        $this->emailCanonical = $emailCanonical;
        $this->usernameCanonical = $emailCanonical;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): self
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetTokenExpiresAt;
    }

    public function setResetTokenExpiresAt(?\DateTimeImmutable $resetTokenExpiresAt): self
    {
        $this->resetTokenExpiresAt = $resetTokenExpiresAt;
        return $this;
    }

    public function isResetTokenValid(): bool
    {
        if (!$this->resetToken || !$this->resetTokenExpiresAt) {
            return false;
        }
        return $this->resetTokenExpiresAt > new \DateTimeImmutable();
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): User
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): User
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get groups={"profile", "invest"},
     */
    public function getBirthday(): ?\DateTimeImmutable
    {
        // Avoir timezone error...
        if ($this->birthday) {
            $this->birthday->setTime(02, 00);
        }

        return $this->birthday;
    }

    /**
     * Set groups={"profile", "invest"},
     */
    public function setBirthday(?\DateTimeImmutable $birthday): User
    {
        $this->birthday = $birthday;

        return $this;
    }

    public function getBirthplace(): ?string
    {
        return $this->birthplace;
    }

    public function setBirthplace(?string $birthplace): User
    {
        $this->birthplace = $birthplace;

        return $this;
    }

    public function getPostalCodeBirthplace(): ?string
    {
        return $this->postalCodeBirthplace;
    }

    public function setPostalCodeBirthplace(?string $postalCodeBirthplace): User
    {
        $this->postalCodeBirthplace = $postalCodeBirthplace;

        return $this;
    }

    public function getInseeCode(): ?string
    {
        return $this->inseeCode;
    }

    public function setInseeCode(?string $inseeCode): User
    {
        $this->inseeCode = $inseeCode;

        return $this;
    }

    public function getInseeCodeBirthplace(): ?string
    {
        return $this->inseeCodeBirthplace;
    }

    public function setInseeCodeBirthplace(?string $inseeCodeBirthplace): User
    {
        $this->inseeCodeBirthplace = $inseeCodeBirthplace;

        return $this;
    }

    /**
     * Get groups={"profile", "invest"},
     */
    public function getNationality(): ?string
    {
        return $this->nationality;
    }

    /**
     * Set groups={"profile", "invest"},
     */
    public function setNationality(string $nationality): User
    {
        $this->nationality = $nationality;

        return $this;
    }

    /**
     * Get groups={"profile", "invest"},
     */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /**
     * Set groups={"profile", "invest"},
     */
    public function setPhone(string $phone): User
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): User
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Get groups={"profile", "invest"},
     */
    public function getAddressLine1(): ?string
    {
        return $this->addressLine1;
    }

    /**
     * Set groups={"profile", "invest"},
     *
     * @return  self
     */
    public function setAddressLine1(?string $addressLine1): User
    {
        $this->addressLine1 = $addressLine1;

        return $this;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function setAddressLine2(?string $addressLine2): User
    {
        $this->addressLine2 = $addressLine2;

        return $this;
    }

    public function getTaxAddress(): ?string
    {
        return $this->taxAddress;
    }

    public function setTaxAddress(?string $taxAddress): User
    {
        $this->taxAddress = $taxAddress;

        return $this;
    }

    public function getFullAddress(): string
    {
        return $this->addressLine1.' '.$this->addressLine2.', '.$this->postalCode.' '.$this->city.', '.$this->country;
    }

    public function getSemiFullAddress(): string
    {
        if (empty($this->addressLine1)) {
            return $this->postalCode.' '.$this->city.', '.$this->country;
        }

        return $this->addressLine1.', '.$this->postalCode.' '.$this->city.', '.$this->country;
    }

    /**
     * Get groups={"profile", "invest"},
     */
    public function getCity(): ?string
    {
        return $this->city;
    }

    /**
     * Set groups={"profile", "invest"},
     */
    public function setCity(?string $city): User
    {
        $this->city = $city;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): User
    {
        $this->region = $region;

        return $this;
    }

    /**
     * Get groups={"profile", "invest"},
     */
    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    /**
     * Set groups={"profile", "invest"},
     */
    public function setPostalCode(?string $postalCode): User
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    /**
     * Get groups={"profile", "invest"},
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    /**
     * Set groups={"profile", "invest"},
     */
    public function setCountry(string $country): User
    {
        $this->country = $country;

        return $this;
    }

    public function getImageFile()
    {
        return $this->imageFile;
    }

    public function setImageFile($imageFile): User
    {
        $this->imageFile = $imageFile;

        if ($imageFile) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->lastLogin = new \DateTime('now');
        }

        return $this;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(string $imageName): User
    {
        $this->imageName = $imageName;

        return $this;
    }

    /**
     * Get groups={"profile", "invest"},
     */
    public function getGender(): ?string
    {
        return $this->gender;
    }

    /**
     * Set groups={"profile", "invest"},
     */
    public function setGender(string $gender): User
    {
        $this->gender = $gender;

        return $this;
    }

    public function getMaritalStatus(): ?string
    {
        return $this->maritalStatus;
    }

    public function getMaritalStatuses(): array
    {
        return array_flip(self::MARITAL_STATUS_LIST);
    }

    public function getMaritallist(): array
    {
        return self::MARITAL_STATUS_LIST;
    }

    public function getMaritalStatusName(): ?string
    {
        return self::MARITAL_STATUS_LIST[$this->maritalStatus] ?? null;
    }

    public function setMaritalStatus(string $maritalStatus): User
    {
        $this->maritalStatus = $maritalStatus;

        return $this;
    }

    public function getProfession(): ?string
    {
        return $this->profession;
    }

    public function getProfessions(): array
    {
        return array_flip(self::PROFESSION_LIST);
    }

    public function setProfession(string $profession): User
    {
        $this->profession = $profession;

        return $this;
    }

    public function getProfessionName(): ?string
    {
        return self::PROFESSION_LIST[$this->profession] ?? null;
    }

    public function getFacebook_id(): string
    {
        return $this->facebook_id;
    }

    public function setFacebook_id(string $facebook_id): User
    {
        $this->facebook_id = $facebook_id;

        return $this;
    }

    public function getFacebook_access_token(): string
    {
        return $this->facebook_access_token;
    }

    public function setFacebook_access_token(string $facebook_access_token): User
    {
        $this->facebook_access_token = $facebook_access_token;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(string $googleId): User
    {
        $this->googleId = $googleId;

        return $this;
    }

    public function getGoogle_access_token(): string
    {
        return $this->google_access_token;
    }

    public function setGoogle_access_token(string $google_access_token): User
    {
        $this->google_access_token = $google_access_token;

        return $this;
    }

    public function getLinkedin_id(): string
    {
        return $this->linkedin_id;
    }

    public function setLinkedin_id(string $linkedin_id): User
    {
        $this->linkedin_id = $linkedin_id;

        return $this;
    }

    public function getLinkedin_access_token(): string
    {
        return $this->linkedin_access_token;
    }

    public function setLinkedin_access_token(string $linkedin_access_token): User
    {
        $this->linkedin_access_token = $linkedin_access_token;

        return $this;
    }

    public function getWants(): ?Want
    {
        return $this->wants;
    }

    public function setWants(Want $wants): User
    {
        $this->wants = $wants;

        return $this;
    }

    public function getInfo(): ?Info
    {
        return $this->info;
    }

    public function setInfo(Info $info): User
    {
        $this->info = $info;

        return $this;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function setDocument(Document $document): User
    {
        $this->document = $document;

        return $this;
    }

    public function getKycDocuments(): PersistentCollection
    {
        return $this->kycDocuments;
    }

    public function setKycDocuments(KycDocument $kycDocuments): User
    {
        $this->kycDocuments = $kycDocuments;

        return $this;
    }

    public function getPosts(): array
    {
        return $this->posts;
    }

    public function addPost(Post $post): User
    {
        $this->posts[] = $post;

        return $this;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): User
    {
        $this->type = $type;

        return $this;
    }

    public function getPro(): ?Pro
    {
        return $this->pro;
    }

    public function setPro(?Pro $pro): User
    {
        $pro->setUser($this);
        $this->pro = $pro;

        return $this;
    }

    public function getRetargeted(): bool
    {
        return $this->retargeted;
    }

    public function setRetargeted(bool $retargeted): User
    {
        $this->retargeted = $retargeted;

        return $this;
    }

    public function getMails()
    {
        return $this->mails;
    }

    public function addMail(Mail $mail): User
    {
        $this->mails[] = $mail;

        return $this;
    }

    public function getSponsorship(): bool
    {
        if ($this->getType() == self::USER_TYPE_CGP) {
            $this->sponsorship = TRUE;
        }

        return $this->sponsorship;
    }

    public function setSponsorship(bool $sponsorship): User
    {
        $this->sponsorship = $sponsorship;

        return $this;
    }

    public function getPipedrive_id(): ?int
    {
        return $this->pipedrive_id;
    }

    public function setPipedrive_id(int $pipedrive_id): User
    {
        $this->pipedrive_id = $pipedrive_id;

        return $this;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): User
    {
        $this->ip = $ip;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): User
    {
        $this->source = $source;

        return $this;
    }

    public function getFullSource(): ?string
    {
        $fullsource = NULL;

        if (!empty($this->howKnown)) {
            $fullsource = $this->howKnown;
        } elseif(!empty($this->source)) {
            $fullsource = $this->source;
        }

        return $fullsource;
    }

    public function getHowKnown(): ?string
    {
        return $this->howKnown;
    }

    public function setHowKnown(string $howKnown): User
    {
        $this->howKnown = $howKnown;

        return $this;
    }

    public function getRisk1(): ?bool
    {
        return $this->risk1;
    }

    public function setRisk1(bool $risk1): User
    {
        $this->risk1 = $risk1;

        return $this;
    }

    public function getRisk2(): ?bool
    {
        return $this->risk2;
    }

    public function setRisk2(bool $risk2): User
    {
        $this->risk2 = $risk2;

        return $this;
    }

    public function getStepRegistrationToken(): ?string
    {
        return $this->stepRegistrationToken;
    }

    public function setStepRegistrationToken(?string $stepRegistrationToken): User
    {
        $this->stepRegistrationToken = $stepRegistrationToken;

        return $this;
    }

    public function getBirthDepartment(): ?string
    {
        return $this->birthDepartment;
    }

    public function setBirthDepartment(?string $birthDepartment): User
    {
        $this->birthDepartment = $birthDepartment;

        return $this;
    }

    public function getBirthCountry(): ?string
    {
        return $this->birthCountry;
    }

    public function setBirthCountry(?string $birthCountry): User
    {
        $this->birthCountry = $birthCountry;

        return $this;
    }

    public static function getGenders(): array
    {
        return [
            'Monsieur' => self::GENDER_MAN,
            'Madame' => self::GENDER_WOMAN
        ];
    }

    public function getGenderLabel(): ?string
    {
        $genders = [
            self::GENDER_MAN => 'Monsieur',
            self::GENDER_WOMAN => 'Madame'
        ];

        return $genders[$this->gender];
    }

    public function isPro(): bool
    {
        return $this->type === self::USER_TYPE_PRO;
    }

    public function isPrivate(): bool
    {
        return $this->type === self::USER_TYPE_PRIVATE;
    }

    public function isCgp(): bool
    {
        return $this->type === self::USER_TYPE_CGP;
    }

    public function getTypeName(string $type = null): string
    {
        $type = empty($type) ? $this->type: $type;

        switch ($type) {
            case self::USER_TYPE_PRO:
                return 'Professionnel';
            case self::USER_TYPE_CGP:
                return 'cgp';
            default:
                return 'Particulier';
        }
    }

    public static function getTypeList(): array
    {
        return [
            self::USER_TYPE_PRIVATE => 'Particulier',
            self::USER_TYPE_PRO     => 'Professionnel',
            self::USER_TYPE_CGP     => 'cgp',
        ];
    }

    public function setRoles(?array $roles): self
    {
        $this->roles = $roles ?? [];
        return $this;
    }

    public function addRole(string $role): self
    {
        $role = strtoupper($role);
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
        return $this;
    }

    public function removeRole(string $role): self
    {
        $role = strtoupper($role);
        $this->roles = array_filter($this->roles, fn ($r) => $r !== $role);
        return $this;
    }

    public function hasRole(string $role): bool
    {
        return in_array(strtoupper($role), $this->getRoles(), true);
    }

    public function getIdentified(): bool
    {
        return $this->hasRole('ROLE_USER_IDENTIFIED');
    }

    public function getUserIdentified(): string
    {
        return $this->hasRole('ROLE_USER_IDENTIFIED') ? 'Oui' : 'Non';
    }

    public function isAdmin(): bool
    {
        foreach (self::ROLES_ADMIN_LIST as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function getComments()
    {
        return $this->comments;
    }

    public function addComment(Comment $comment)
    {
        $this->comments[] = $comment;
        $comment->setUser($this);

        return $this;
    }

    public function getControl(): ?Control
    {
        return $this->control;
    }

    public function setControl($control): self
    {
        $this->control = $control;

        return $this;
    }


    public function getCgp(): ?Cgp
    {
        return $this->cgp;
    }

    public function setCgp(?Cgp $cgp): self
    {
        $this->cgp = $cgp;
        $cgp->setUser($this);

        return $this;
    }

    public function getCosigner(): ?bool
    {
        return $this->cosigner;
    }

    public function setCosigner(?bool $cosigner): self
    {
       $this->cosigner = $cosigner;

       return $this;
    }

    /**
     * @throws Exception
     */
    public function getAge(): int
    {
        $today = new \DateTimeImmutable(date('c'));
        $age = $today->diff($this->getBirthday());

        return $age->y;
    }


    public function isKycOutdated(): bool
    {
        return $this->hasRole(self::ROLE_KYC_OUTDATED);
    }

    public function isBlocked(): bool
    {
        return $this->hasRole(self::ROLE_USER_BLOCKED);
    }

    public function getMailing(): ?Mailing
    {
        return $this->mailing;
    }

    public function setMailing(?Mailing $mailing): self
    {
        $this->mailing = $mailing;
        $mailing->setUser($this);

        return $this;
    }

    public function isPrivateOrProfessional(): bool
    {
        return ($this->type === self::USER_TYPE_PRIVATE || $this->type === self::USER_TYPE_PRO);
    }

    public function getInterestedBy(): ?array
    {
        return $this->interestedBy;
    }

    public function setInterestedBy(?array $investedBy): self
    {
        $this->interestedBy = $investedBy;

        return $this;
    }

    public function getInterestedByTxt(): ?string
    {
        if(false === empty($this->interestedBy))
        {
            $result = array_intersect_key(self::INTERESTED_BY, array_flip($this->interestedBy));

            return implode(';', $result);
        }

        return null;
    }

    public function getRolesList(): array
    {
        return self::ROLES_LIST;
    }

    public function getRoleColor($role)
    {
        return self::ROLE_COLORS_LIST[$role] ?? null;
    }

    public function setConfig(?Config $config): User
    {
        $config->setUser($this);
        $this->config = $config;
        return $this;
    }

    public function getConfig()
    {
        return $this->config;
    }

    // Compat: méthodes neutralisées
    public function getMarketing()
    {
        return null;
    }

    public function setMarketing($marketing): self
    {
        return $this;
    }

    public function getAdditionalInfo(): ?AdditionalInfo
    {
        return $this->additionalInfo;
    }

    public function setAdditionalInfo(AdditionalInfo $additionalInfo): self
    {
        $this->additionalInfo = $additionalInfo;

        return $this;
    }

    public function getIsAwareProfile(): ?bool
    {
        return $this->isAwareProfile;
    }

    public function setIsAwareProfile(?bool $isAwareProfile): self
    {
        $this->isAwareProfile = $isAwareProfile;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription($description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getStepKyc(): ?int
    {
        return $this->stepKyc;
    }

    public function setStepKyc(int $stepKyc): self
    {
        $this->stepKyc = $stepKyc;

        return $this;
    }

    public function getTaxResidence(): ?string
    {
        return $this->taxResidence;
    }

    /**
     * @param string $taxResidence
     * @return User
     */
    public function setTaxResidence(string $taxResidence): self
    {
        $this->taxResidence = $taxResidence;
        return $this;
    }

    /**
     * Desactive le PreUpdate
     * @return void
     */
    public function disableAutoUpdate(): void
    {
        $this->preventAutoUpdate = true;
    }

    public function getRoles(): array
    {

        $roles = $this->roles;
        $roles[] = self::ROLE_USER;

        return array_unique($roles);
    }

    /**
     * Retourne les rôles bruts (sans l'ajout automatique de ROLE_USER)
     */
    public function getRolesRaw(): array
    {
        return $this->roles ?? [];
    }

    public function getUserIdentifier(): string
    {
        return $this->email ?? '';
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $hashPassword): self
    {
        $this->password = $hashPassword;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getKycStepLabel(): string
    {
        $labels = [
            1 => 'Profil',
            2 => 'Objectifs',
            3 => 'Patrimoine',
            4 => 'Expérience',
            5 => 'Documents',
        ];
        if ($this->stepKyc === null) {
            return 'Non commencé';
        }
        return $labels[$this->stepKyc] ?? 'Étape inconnue (' . $this->stepKyc . ')';
    }

    /**
     * Retourne le rôle principal de l'utilisateur sous forme de chaîne lisible
     */
    public function getRolesDisplay(): string
    {
        $roles = $this->getRoles();
        if (empty($roles)) {
            return 'Utilisateur';
        }

        // Priorité des rôles admin (dans l'ordre de la hiérarchie)
        $priorityOrder = [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_ADMIN_OPERATOR,
            self::ROLE_ADMIN_KYC,
            self::ROLE_ADMIN_SUPPORT,
            self::ROLE_ADMIN_MARKETING,
        ];

        foreach ($priorityOrder as $role) {
            if (in_array($role, $roles)) {
                return self::ROLES_ADMIN_LABELS[$role] ?? $role;
            }
        }
        
        return 'Utilisateur';
    }

    /**
     * Vérifie si l'utilisateur a accès au back-office
     */
    public function hasBackofficeAccess(): bool
    {
        foreach (array_keys(self::ROLES_ADMIN_LIST) as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifie si l'utilisateur peut accéder à un module spécifique du back-office
     */
    public function canAccessModule(string $module): bool
    {
        foreach (self::ROLES_PERMISSIONS as $role => $permissions) {
            if ($this->hasRole($role)) {
                // Accès total si permission '*'
                if (in_array('*', $permissions)) {
                    return true;
                }
                if (in_array($module, $permissions)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Retourne les rôles admin de l'utilisateur
     */
    public function getAdminRoles(): array
    {
        return array_intersect($this->getRoles(), array_keys(self::ROLES_ADMIN_LIST));
    }

    /**
     * Retourne les labels des rôles admin
     */
    public static function getAdminRolesLabels(): array
    {
        return self::ROLES_ADMIN_LABELS;
    }

    /**
     * Vérifie si l'utilisateur est Super Admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    #[ORM\OneToMany(targetEntity: InvestorKnowledge::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private $investorKnowledge;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $investorScore = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $investorProfile = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $investorProfileCalculatedAt = null;

    public function getInvestorKnowledge(): ?InvestorKnowledge
    {
        return $this->investorKnowledge->first() ?: null;
    }

    public function setInvestorKnowledge(?InvestorKnowledge $investorKnowledge): self
    {
        if ($this->investorKnowledge->contains($investorKnowledge)) {
            $this->investorKnowledge->removeElement($investorKnowledge);
        }
        
        if ($investorKnowledge) {
            $this->investorKnowledge->add($investorKnowledge);
            $investorKnowledge->setUser($this);
        }
        
        return $this;
    }

    public function getInvestorScore(): ?int
    {
        return $this->investorScore;
    }

    public function setInvestorScore(?int $investorScore): self
    {
        $this->investorScore = $investorScore;
        return $this;
    }

    public function getInvestorProfile(): ?string
    {
        return $this->investorProfile;
    }

    public function setInvestorProfile(?string $investorProfile): self
    {
        $this->investorProfile = $investorProfile;
        return $this;
    }

    public function getInvestorProfileCalculatedAt(): ?\DateTimeImmutable
    {
        return $this->investorProfileCalculatedAt;
    }

    public function setInvestorProfileCalculatedAt(?\DateTimeImmutable $investorProfileCalculatedAt): self
    {
        $this->investorProfileCalculatedAt = $investorProfileCalculatedAt;
        return $this;
    }

    public function getQuestionnaireStatus(): string
    {
        $investorKnowledge = $this->getInvestorKnowledge();
        
        if (!$investorKnowledge) {
            return 'Non commencé';
        }
        
        // Vérifier si le questionnaire est complet
        $financialProducts = $investorKnowledge->getFinancialProductsKnowledge();
        $complexProducts = $investorKnowledge->getComplexProductsKnowledge();
        $marketExperience = $investorKnowledge->getMarketExperience();
        $educationLevel = $investorKnowledge->getEducationLevel();
        
        $completedSections = 0;
        $totalSections = 4;
        
        if ($financialProducts && $financialProducts->isComplete()) {
            $completedSections++;
        }
        if ($complexProducts && $complexProducts->isComplete()) {
            $completedSections++;
        }
        if ($marketExperience && $marketExperience->isComplete()) {
            $completedSections++;
        }
        if ($educationLevel && $educationLevel->getLevel()) {
            $completedSections++;
        }
        
        if ($completedSections === 0) {
            return 'Non commencé';
        } elseif ($completedSections === $totalSections) {
            return 'Complété';
        } else {
            return "Partiel ({$completedSections}/{$totalSections})";
        }
    }

    /**
     * Vérifie si le mode sombre admin est activé pour cet utilisateur
     */
    public function isAdminDarkMode(): bool
    {
        return $this->adminDarkMode;
    }

    /**
     * Active ou désactive le mode sombre admin
     */
    public function setAdminDarkMode(bool $adminDarkMode): static
    {
        $this->adminDarkMode = $adminDarkMode;
        return $this;
    }

    // ==================== SUSPENSION METHODS ====================

    /**
     * Vérifie si l'utilisateur est suspendu
     */
    public function isSuspended(): bool
    {
        return $this->suspendedAt !== null;
    }

    /**
     * Récupère la date de suspension
     */
    public function getSuspendedAt(): ?\DateTimeImmutable
    {
        return $this->suspendedAt;
    }

    /**
     * Définit la date de suspension
     */
    public function setSuspendedAt(?\DateTimeImmutable $suspendedAt): static
    {
        $this->suspendedAt = $suspendedAt;
        return $this;
    }

    /**
     * Récupère la raison de suspension
     */
    public function getSuspendedReason(): ?string
    {
        return $this->suspendedReason;
    }

    /**
     * Définit la raison de suspension
     */
    public function setSuspendedReason(?string $suspendedReason): static
    {
        $this->suspendedReason = $suspendedReason;
        return $this;
    }

    /**
     * Suspend l'utilisateur avec une raison optionnelle
     */
    public function suspend(?string $reason = null): static
    {
        $this->suspendedAt = new \DateTimeImmutable();
        $this->suspendedReason = $reason;
        return $this;
    }

    /**
     * Réactive l'utilisateur suspendu
     */
    public function unsuspend(): static
    {
        $this->suspendedAt = null;
        $this->suspendedReason = null;
        return $this;
    }

    // ==================== MONEYPITCH REDIRECT METHODS ====================

    /**
     * Vérifie si l'utilisateur doit être redirigé vers MoneyPitch après connexion
     */
    public function isRedirectToMoneyPitch(): bool
    {
        return $this->redirectToMoneyPitch;
    }

    /**
     * Définit si l'utilisateur doit être redirigé vers MoneyPitch après connexion
     */
    public function setRedirectToMoneyPitch(bool $redirectToMoneyPitch): static
    {
        $this->redirectToMoneyPitch = $redirectToMoneyPitch;
        return $this;
    }

    /**
     * Vérifie si l'utilisateur doit être redirigé vers MoneyPitch
     * (prend en compte le flag ET le fait que l'utilisateur ne soit pas admin)
     */
    public function shouldRedirectToMoneyPitch(): bool
    {
        // Si le flag est désactivé, pas de redirection
        if (!$this->redirectToMoneyPitch) {
            return false;
        }

        // Si l'utilisateur est admin, pas de redirection
        if ($this->isAdmin()) {
            return false;
        }

        return true;
    }

    // ==================== O2S INTEGRATION METHODS ====================

    /**
     * Get the O2S Contact ID linked to this user.
     */
    public function getO2sContactId(): ?string
    {
        return $this->o2sContactId;
    }

    /**
     * Set the O2S Contact ID.
     */
    public function setO2sContactId(?string $o2sContactId): static
    {
        $this->o2sContactId = $o2sContactId;
        return $this;
    }

    /**
     * Get the last O2S synchronization timestamp.
     */
    public function getO2sSyncedAt(): ?\DateTimeImmutable
    {
        return $this->o2sSyncedAt;
    }

    /**
     * Set the last O2S synchronization timestamp.
     */
    public function setO2sSyncedAt(?\DateTimeImmutable $o2sSyncedAt): static
    {
        $this->o2sSyncedAt = $o2sSyncedAt;
        return $this;
    }

    public function getO2sTypeContact(): ?string
    {
        return $this->o2sTypeContact;
    }

    public function setO2sTypeContact(?string $o2sTypeContact): static
    {
        $this->o2sTypeContact = $o2sTypeContact;
        return $this;
    }

    /**
     * Check if user is linked to an O2S contact.
     */
    public function isLinkedToO2S(): bool
    {
        return $this->o2sContactId !== null;
    }

    /**
     * Check if O2S data needs refresh (older than given hours).
     */
    public function needsO2SRefresh(int $maxAgeHours = 24): bool
    {
        if (!$this->isLinkedToO2S()) {
            return false;
        }

        if ($this->o2sSyncedAt === null) {
            return true;
        }

        $threshold = new \DateTimeImmutable(sprintf('-%d hours', $maxAgeHours));
        return $this->o2sSyncedAt < $threshold;
    }
}