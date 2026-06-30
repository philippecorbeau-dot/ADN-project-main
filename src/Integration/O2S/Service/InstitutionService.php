<?php

declare(strict_types=1);

namespace App\Integration\O2S\Service;

use App\Integration\O2S\Client\O2SClientInterface;
use App\Integration\O2S\Config\O2SConfiguration;
use App\Integration\O2S\DTO\Institution\InstitutionDTO;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service for managing O2S Institutions (financial establishments).
 */
final class InstitutionService implements InstitutionServiceInterface
{
    private const ENDPOINT = '/institutions';

    public function __construct(
        private readonly O2SClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $o2sCache,
    ) {
    }

    private function getBaseUrl(): string
    {
        return $this->client->getConfiguration()->getApiUrl();
    }

    public function getInstitution(string $institutionId): InstitutionDTO
    {
        $cacheKey = O2SConfiguration::CACHE_KEY_PREFIX . 'institution_' . $institutionId;

        return $this->o2sCache->get($cacheKey, function (ItemInterface $item) use ($institutionId) {
            $item->expiresAfter(86400); // Cache for 24 hours (reference data)

            $this->logger->debug('Fetching O2S institution', ['institutionId' => $institutionId]);
            $data = $this->client->get(self::ENDPOINT . '/' . $institutionId, [], $this->getBaseUrl());

            return InstitutionDTO::fromApiResponse($data);
        });
    }

    public function getAllInstitutions(): array
    {
        $cacheKey = O2SConfiguration::CACHE_KEY_PREFIX . 'institutions_all';

        return $this->o2sCache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter(86400); // Cache for 24 hours

            $this->logger->debug('Fetching all O2S institutions');
            
            $allInstitutions = [];
            $offset = 0;
            $limit = 100;

            do {
                $data = $this->client->get(self::ENDPOINT, [
                    'limit' => $limit,
                    'offset' => $offset,
                ], $this->getBaseUrl());

                if (!is_array($data)) {
                    break;
                }

                foreach ($data as $item) {
                    $allInstitutions[] = InstitutionDTO::fromApiResponse($item);
                }

                $offset += $limit;
            } while (count($data) === $limit);

            $this->logger->info('Retrieved all O2S institutions', ['count' => count($allInstitutions)]);

            return $allInstitutions;
        });
    }

    public function getInstitutionsMap(): array
    {
        $institutions = $this->getAllInstitutions();
        $map = [];

        foreach ($institutions as $institution) {
            $map[$institution->getInstitutionId()] = $institution->getLabel() ?? $institution->getInstitutionId();
        }

        return $map;
    }
}

