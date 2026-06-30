<?php

declare(strict_types=1);

namespace App\Integration\O2S\DTO\Contact;

/**
 * Data Transfer Object for O2S Contact.
 * 
 * Represents a contact (client) from O2S system.
 */
final class ContactDTO
{
    /**
     * @param array<string, mixed> $rawData Original data from O2S API
     */
    public function __construct(
        private readonly string $id,
        private readonly ?string $civilite,
        private readonly ?string $nom,
        private readonly ?string $nomNaissance,
        private readonly array $prenoms,
        private readonly ?string $email,
        private readonly ?string $telephone,
        private readonly ?string $telephoneMobile,
        private readonly ?\DateTimeImmutable $dateNaissance,
        private readonly ?string $lieuNaissance,
        private readonly ?AddressDTO $adresse,
        private readonly ?string $profession,
        private readonly ?\DateTimeImmutable $dateCreation,
        private readonly ?\DateTimeImmutable $dateMaj,
        private readonly array $refExternes,
        private readonly ?string $typeContact,
        private readonly array $rawData,
    ) {
    }

    /**
     * Creates a ContactDTO from O2S API response.
     * 
     * @param array<string, mixed> $data
     */
    public static function fromApiResponse(array $data): self
    {
        // Handle different API response formats
        // Some endpoints return 'personne.donneesNominatives', others 'donneesPersonnelles'
        $personne = $data['personne'] ?? [];
        $donneesNom = $personne['donneesNominatives'] ?? [];
        $donneesPerso = $data['donneesPersonnelles'] ?? $donneesNom;
        
        // moyensContact can be under personne or donneesPersonnelles
        $moyensContact = $personne['moyensContact'] ?? $donneesPerso['moyensContact'] ?? [];
        $naissance = $personne['naissance'] ?? $donneesPerso['naissance'] ?? [];

        // Extract emails
        $email = null;
        foreach ($moyensContact['emails'] ?? [] as $e) {
            if (isset($e['adresse'])) {
                $email = $e['adresse'];
                break;
            }
        }

        // Extract phones
        $telephone = null;
        $telephoneMobile = null;
        foreach ($moyensContact['telephones'] ?? [] as $tel) {
            if (($tel['type'] ?? '') === 'MOBILE' && $telephoneMobile === null) {
                $telephoneMobile = $tel['numero'] ?? null;
            } elseif ($telephone === null) {
                $telephone = $tel['numero'] ?? null;
            }
        }

        // Parse dates
        $dateNaissance = isset($naissance['dateNaissance'])
            ? self::parseDate($naissance['dateNaissance'])
            : null;

        $dateCreation = isset($data['dateCreation'])
            ? self::parseDateTime($data['dateCreation'])
            : null;

        $dateMaj = isset($data['dateMaj'])
            ? self::parseDateTime($data['dateMaj'])
            : null;

        // Build address DTO
        $adresse = null;
        if (!empty($moyensContact['adresse'])) {
            $adresse = AddressDTO::fromApiResponse($moyensContact['adresse']);
        }

        $typeContact = $data['informationsCommerciales']['typeContact'] ?? null;

        return new self(
            id: $data['id'] ?? '',
            civilite: $donneesPerso['civilite'] ?? $donneesNom['civilite'] ?? null,
            nom: $donneesPerso['nom'] ?? $donneesNom['nom'] ?? null,
            nomNaissance: $donneesPerso['nomNaissance'] ?? $donneesNom['nomNaissance'] ?? null,
            prenoms: $donneesPerso['prenoms'] ?? $donneesNom['prenoms'] ?? [],
            email: $email,
            telephone: $telephone,
            telephoneMobile: $telephoneMobile,
            dateNaissance: $dateNaissance,
            lieuNaissance: $naissance['lieuNaissance'] ?? null,
            adresse: $adresse,
            profession: $donneesPerso['profession'] ?? null,
            dateCreation: $dateCreation,
            dateMaj: $dateMaj,
            refExternes: $data['refExternes'] ?? [],
            typeContact: $typeContact,
            rawData: $data,
        );
    }

    private static function parseDate(string $date): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($date);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function parseDateTime(string $datetime): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($datetime);
        } catch (\Throwable) {
            return null;
        }
    }

    // Getters

    public function getId(): string
    {
        return $this->id;
    }

    public function getCivilite(): ?string
    {
        return $this->civilite;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function getNomNaissance(): ?string
    {
        return $this->nomNaissance;
    }

    /**
     * @return string[]
     */
    public function getPrenoms(): array
    {
        return $this->prenoms;
    }

    public function getPrenom(): ?string
    {
        return $this->prenoms[0] ?? null;
    }

    public function getFullName(): string
    {
        $parts = [];
        if (!empty($this->prenoms)) {
            $parts[] = $this->prenoms[0];
        }
        if ($this->nom) {
            $parts[] = $this->nom;
        }
        return implode(' ', $parts);
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function getTelephoneMobile(): ?string
    {
        return $this->telephoneMobile;
    }

    public function getDateNaissance(): ?\DateTimeImmutable
    {
        return $this->dateNaissance;
    }

    public function getLieuNaissance(): ?string
    {
        return $this->lieuNaissance;
    }

    public function getAdresse(): ?AddressDTO
    {
        return $this->adresse;
    }

    public function getProfession(): ?string
    {
        return $this->profession;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function getDateMaj(): ?\DateTimeImmutable
    {
        return $this->dateMaj;
    }

    /**
     * Harvest CRM classification: "Client", "Prospect", etc.
     */
    public function getTypeContact(): ?string
    {
        return $this->typeContact;
    }

    /**
     * @return array<string, string>
     */
    public function getRefExternes(): array
    {
        return $this->refExternes;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * Finds external reference by referential name.
     */
    public function getExternalRef(string $referential): ?string
    {
        return $this->refExternes[$referential] ?? null;
    }
}


