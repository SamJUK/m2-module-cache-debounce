<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model\Storage;

interface QueueStorageInterface
{
    /**
     * Queue tags for a future purge. Must be idempotent and no-op on empty.
     */
    public function add(array $tags): void;

    /**
     * Atomically carve off pending tags into a new batch; '' if none.
     */
    public function claim(): string;

    /**
     * Read the tags belonging to a previously claimed batch.
     */
    public function tags(string $batchId): array;

    /**
     * Delete a claimed batch. Never touches pending (unclaimed) rows.
     */
    public function clear(string $batchId): void;

    /**
     * Release a claimed batch back to pending, merging idempotently.
     */
    public function release(string $batchId): void;

    /**
     * Id of an already-claimed, not-yet-cleared batch, if one exists.
     * Returns '' if nothing is currently claimed.
     */
    public function activeBatch(): string;
}
