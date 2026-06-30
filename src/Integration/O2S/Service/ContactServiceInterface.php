<?php

declare(strict_types=1);

namespace App\Integration\O2S\Service;

use App\Integration\O2S\DTO\Contact\ContactDTO;
use App\Integration\O2S\DTO\Contact\PatrimoineDTO;

/**
 * Interface for O2S Contact operations.
 */
interface ContactServiceInterface
{
    /**
     * Retrieves a single contact by ID.
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function getContact(string $contactId): ContactDTO;

    /**
     * Retrieves contacts with optional filtering.
     * 
     * @param array<string, mixed> $filters
     * @return ContactDTO[]
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function getContacts(array $filters = [], int $limit = 20, int $offset = 0): array;

    /**
     * Finds a contact by email.
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function findByEmail(string $email): ?ContactDTO;

    /**
     * Finds a contact by external reference.
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function findByExternalRef(string $referential, string $value): ?ContactDTO;

    /**
     * Creates a new contact in O2S.
     * 
     * @param array<string, mixed> $data
     * @return string The new contact ID
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function createContact(array $data): string;

    /**
     * Updates an existing contact.
     * 
     * @param array<string, mixed> $data
     * 
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function updateContact(string $contactId, array $data): void;

    /**
     * Retrieves patrimoine (wealth) data for a contact.
     * 
     * Uses GET /contacts/{contactId} and parses patrimoine-related fields.
     *
     * @throws \App\Integration\O2S\Exception\O2SApiException
     */
    public function getContactPatrimoine(string $contactId): PatrimoineDTO;
}


