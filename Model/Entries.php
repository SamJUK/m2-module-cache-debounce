<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model;

use SamJUK\CacheDebounce\Model\Config;
use SamJUK\CacheDebounce\Model\Storage\QueueStorageInterface;
use Magento\CacheInvalidate\Model\PurgeCache;
use Psr\Log\LoggerInterface;

class Entries
{
    /** @var QueueStorageInterface $storage */
    private $storage;

    /** @var PurgeCache $purgeCache */
    private $purgeCache;

    /** @var Config $config */
    private $config;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        Config $config,
        PurgeCache $purgeCache,
        QueueStorageInterface $storage,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->purgeCache = $purgeCache;
        $this->storage = $storage;
        $this->logger = $logger;
    }

    /**
     * Add new tags to the purge queue
     */
    public function add(array $tags) : void
    {
        $this->storage->add($tags);
    }

    /**
     * Purge all queued tags, and clear the queue
     */
    public function flush() : void
    {
        $batchId = $this->storage->claim();
        if ($batchId === '') {
            $this->logger->debug("[CacheDebounce] Nothing to flush");
            return;
        }

        $tags = $this->storage->tags($batchId);
        $this->logger->debug("[CacheDebounce] Flushing Tags: " . json_encode($tags));
        $this->config->setShouldDebouncePurgeRequest(false);

        try {
            $success = $this->purgeCache->sendPurgeRequest($tags);

            if (!$success) {
                $this->logger->error(
                    "[CacheDebounce] Purge request failed — releasing batch $batchId for retry: "
                        . json_encode($tags)
                );
                $this->storage->release($batchId);
                return;
            }

            $this->storage->clear($batchId);
        } catch (\Throwable $e) {
            $this->logger->error(
                "[CacheDebounce] Purge request threw — releasing batch $batchId for retry: " . $e->getMessage()
            );
            $this->storage->release($batchId);
            throw $e;
        } finally {
            $this->config->setShouldDebouncePurgeRequest(true);
        }
    }
}
