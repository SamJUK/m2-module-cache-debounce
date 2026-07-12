<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model\Storage;

use SamJUK\CacheDebounce\Model\Storage\Redis\ConnectionResolver;

class Redis implements QueueStorageInterface
{
    private const KEY_LIVE = 'samjuk_cache_debounce:queue:live';
    private const KEY_BATCH_PREFIX = 'samjuk_cache_debounce:queue:batch:';
    private const ACTIVE_BATCH_ID = 'active';

    /** @var ConnectionResolver $connectionResolver */
    private $connectionResolver;

    public function __construct(
        ConnectionResolver $connectionResolver
    ) {
        $this->connectionResolver = $connectionResolver;
    }

    /**
     * @inheritDoc
     */
    public function add(array $tags): void
    {
        if (!$tags) {
            return;
        }

        $this->connectionResolver->getClient()->sAdd(self::KEY_LIVE, ...$tags);
    }

    /**
     * @inheritDoc
     */
    public function claim(): string
    {
        if ($this->activeBatch() !== '') {
            return '';
        }

        $client = $this->connectionResolver->getClient();

        try {
            $client->rename(self::KEY_LIVE, $this->batchKey(self::ACTIVE_BATCH_ID));
        } catch (\CredisException $e) {
            if (stripos($e->getMessage(), 'no such key') !== false) {
                return '';
            }
            throw $e;
        }

        return self::ACTIVE_BATCH_ID;
    }

    /**
     * @inheritDoc
     */
    public function tags(string $batchId): array
    {
        return $this->connectionResolver->getClient()->sMembers($this->batchKey($batchId));
    }

    /**
     * @inheritDoc
     */
    public function clear(string $batchId): void
    {
        $this->connectionResolver->getClient()->del($this->batchKey($batchId));
    }

    /**
     * @inheritDoc
     */
    public function release(string $batchId): void
    {
        $client = $this->connectionResolver->getClient();
        $client->sUnionStore(self::KEY_LIVE, self::KEY_LIVE, $this->batchKey($batchId));
        $client->del($this->batchKey($batchId));
    }

    /**
     * @inheritDoc
     */
    public function activeBatch(): string
    {
        return $this->connectionResolver->getClient()->sCard($this->batchKey(self::ACTIVE_BATCH_ID)) > 0
            ? self::ACTIVE_BATCH_ID
            : '';
    }

    private function batchKey(string $batchId): string
    {
        return self::KEY_BATCH_PREFIX . $batchId;
    }
}
