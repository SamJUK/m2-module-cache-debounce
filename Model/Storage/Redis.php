<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model\Storage;

use SamJUK\CacheDebounce\Model\Storage\Redis\ConnectionResolver;

class Redis implements QueueStorageInterface
{
    private const KEY_LIVE = 'samjuk_cache_debounce:queue:live';
    private const KEY_BATCH_PREFIX = 'samjuk_cache_debounce:queue:batch:';

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
        $batchId = bin2hex(random_bytes(16));

        try {
            $this->connectionResolver->getClient()->rename(self::KEY_LIVE, self::KEY_BATCH_PREFIX . $batchId);
        } catch (\CredisException $e) {
            if (stripos($e->getMessage(), 'no such key') !== false) {
                return '';
            }
            throw $e;
        }

        return $batchId;
    }

    /**
     * @inheritDoc
     */
    public function tags(string $batchId): array
    {
        return $this->connectionResolver->getClient()->sMembers(self::KEY_BATCH_PREFIX . $batchId);
    }

    /**
     * @inheritDoc
     */
    public function clear(string $batchId): void
    {
        $this->connectionResolver->getClient()->del(self::KEY_BATCH_PREFIX . $batchId);
    }

    /**
     * @inheritDoc
     */
    public function release(string $batchId): void
    {
        $client = $this->connectionResolver->getClient();
        $client->sUnionStore(self::KEY_LIVE, self::KEY_LIVE, self::KEY_BATCH_PREFIX . $batchId);
        $client->del(self::KEY_BATCH_PREFIX . $batchId);
    }
}
