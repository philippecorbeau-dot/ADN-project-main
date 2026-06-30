<?php

declare(strict_types=1);

namespace App\Integration\O2S\Sync;

/**
 * Result of a synchronization operation.
 * 
 * Mutable object that accumulates sync statistics.
 */
final class SyncResult
{
    private int $created = 0;
    private int $updated = 0;
    private int $skipped = 0;
    /** @var array<string> */
    private array $errors = [];
    /** @var array<string, mixed> */
    private array $metadata = [];

    public function addCreated(): self
    {
        $this->created++;
        return $this;
    }

    public function addUpdated(): self
    {
        $this->updated++;
        return $this;
    }

    public function addSkipped(): self
    {
        $this->skipped++;
        return $this;
    }

    public function addError(string $error): self
    {
        $this->errors[] = $error;
        return $this;
    }

    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function isSuccess(): bool
    {
        return empty($this->errors);
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getTotal(): int
    {
        return $this->created + $this->updated + $this->skipped;
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Returns a specific metadata value by key, or all metadata if no key is provided.
     * 
     * @return mixed
     */
    public function getMetadata(?string $key = null): mixed
    {
        if ($key !== null) {
            return $this->metadata[$key] ?? null;
        }
        return $this->metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->isSuccess(),
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'total' => $this->getTotal(),
            'errors' => $this->errors,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Merges another SyncResult into this one.
     */
    public function merge(SyncResult $other): self
    {
        $this->created += $other->created;
        $this->updated += $other->updated;
        $this->skipped += $other->skipped;
        $this->errors = array_merge($this->errors, $other->errors);
        $this->metadata = array_merge($this->metadata, $other->metadata);
        return $this;
    }

    public function __toString(): string
    {
        $parts = [
            sprintf('Créés: %d', $this->created),
            sprintf('Mis à jour: %d', $this->updated),
            sprintf('Ignorés: %d', $this->skipped),
        ];

        if (!empty($this->errors)) {
            $parts[] = sprintf('Erreurs: %d', count($this->errors));
        }

        return implode(', ', $parts);
    }

    /**
     * Creates a success result with counts.
     */
    public static function success(int $created = 0, int $updated = 0, int $skipped = 0): self
    {
        $result = new self();
        $result->created = $created;
        $result->updated = $updated;
        $result->skipped = $skipped;
        return $result;
    }

    /**
     * Creates a failure result with errors.
     * 
     * @param array<string> $errors
     */
    public static function failure(array $errors): self
    {
        $result = new self();
        $result->errors = $errors;
        return $result;
    }
}
