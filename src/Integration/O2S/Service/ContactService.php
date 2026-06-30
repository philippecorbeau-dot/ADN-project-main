<?php

declare(strict_types=1);

namespace App\Integration\O2S\Service;

use App\Integration\O2S\Client\O2SClientInterface;
use App\Integration\O2S\Config\O2SConfiguration;
use App\Integration\O2S\DTO\Contact\ContactDTO;
use App\Integration\O2S\DTO\Contact\PatrimoineDTO;
use App\Integration\O2S\Exception\O2SApiException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service for managing O2S Contacts.
 */
final class ContactService implements ContactServiceInterface
{
    private const ENDPOINT = '/contacts';

    public function __construct(
        private readonly O2SClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $o2sCache,
    ) {
    }

    private function getBaseUrl(): string
    {
        return $this->client->getConfiguration()->getContactsApiUrl();
    }

    public function getContact(string $contactId): ContactDTO
    {
        $this->logger->debug('Fetching O2S contact', ['id' => $contactId]);

        $data = $this->client->get(self::ENDPOINT . '/' . $contactId, [], $this->getBaseUrl());

        return ContactDTO::fromApiResponse($data);
    }

    public function getContacts(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $this->logger->debug('Fetching O2S contacts', [
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $query = array_merge($filters, [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $data = $this->client->get(self::ENDPOINT, $query, $this->getBaseUrl());

        // Handle both single item and array responses
        if (!isset($data[0]) && !empty($data)) {
            return [ContactDTO::fromApiResponse($data)];
        }

        return array_map(
            fn(array $item) => ContactDTO::fromApiResponse($item),
            $data
        );
    }

    public function findByEmail(string $email): ?ContactDTO
    {
        $this->logger->debug('Finding O2S contact by email', ['email' => $email]);

        try {
            // O2S ne permet pas de filtrer directement par email
            // On récupère tous les contacts et on filtre côté application
            // Note: Dans une vraie implémentation, il faudrait vérifier si O2S offre
            // un endpoint de recherche par email
            $contacts = $this->getContacts(['email' => $email]);

            foreach ($contacts as $contact) {
                if (strtolower($contact->getEmail() ?? '') === strtolower($email)) {
                    return $contact;
                }
            }

            return null;
        } catch (O2SApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    public function findByExternalRef(string $referential, string $value): ?ContactDTO
    {
        $this->logger->debug('Finding O2S contact by external ref', [
            'referential' => $referential,
            'value' => $value,
        ]);

        try {
            $contacts = $this->getContacts([
                'refext' => sprintf('%s:%s', $referential, $value),
            ]);

            return $contacts[0] ?? null;
        } catch (O2SApiException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    public function createContact(array $data): string
    {
        $this->logger->info('Creating O2S contact', [
            'email' => $data['donneesPersonnelles']['moyensContact']['emails'][0]['adresse'] ?? 'N/A',
        ]);

        $response = $this->client->post(self::ENDPOINT, $data, $this->getBaseUrl());

        if (!isset($response['id'])) {
            throw O2SApiException::invalidResponse(self::ENDPOINT, 'Missing id in response');
        }

        return $response['id'];
    }

    public function updateContact(string $contactId, array $data): void
    {
        $this->logger->info('Updating O2S contact', ['id' => $contactId]);

        $this->client->put(self::ENDPOINT . '/' . $contactId, $data, $this->getBaseUrl());
    }

    public function getContactPatrimoine(string $contactId): PatrimoineDTO
    {
        $cacheKey = O2SConfiguration::CACHE_KEY_PREFIX . 'patrimoine_' . md5($contactId);

        return $this->o2sCache->get($cacheKey, function (ItemInterface $item) use ($contactId) {
            $item->expiresAfter(86400); // Cache 24h (rafraîchi par le cron o2s_warm_cache.php à 6h)

            $this->logger->debug('Fetching O2S contact patrimoine (cache miss)', ['id' => $contactId]);
            $data = $this->client->get(self::ENDPOINT . '/' . $contactId, [], $this->getBaseUrl());

            $dto = PatrimoineDTO::fromContactResponse($data);

            $this->logger->debug('O2S patrimoine loaded', [
                'contactId' => $contactId,
                'passif' => $dto->getPassifs(),
                'actif' => $dto->getTotalActif(),
                'net' => $dto->getPatrimoineNet(),
            ]);

            return $dto;
        });
    }

    /**
     * Retrieves all contacts (paginated internally).
     * 
     * @param array<string, mixed> $filters
     * @return ContactDTO[]
     */
    public function getAllContacts(array $filters = []): array
    {
        $allContacts = [];
        $offset = 0;
        $limit = 100;

        do {
            $contacts = $this->getContacts($filters, $limit, $offset);
            $allContacts = array_merge($allContacts, $contacts);
            $offset += $limit;
        } while (count($contacts) === $limit);

        $this->logger->info('Retrieved all O2S contacts', ['count' => count($allContacts)]);

        return $allContacts;
    }
}


