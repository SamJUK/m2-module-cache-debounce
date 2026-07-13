<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model;

use Magento\CacheInvalidate\Model\PurgeCache;
use Magento\Framework\FlagManager;
use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;
use SamJUK\CacheDebounce\Model\Storage\QueueStorageInterface;

class StaggeredFlush
{
    private const LOCK_NAME = 'samjuk_cache_debounce_flush';
    private const LOCK_TIMEOUT_FAIL_FAST = 0;

    /** @var Config $config */
    private $config;

    /** @var QueueStorageInterface $storage */
    private $storage;

    /** @var PurgeCache $purgeCache */
    private $purgeCache;

    /** @var LockManagerInterface $lockManager */
    private $lockManager;

    /** @var LagDetector $lagDetector */
    private $lagDetector;

    /** @var FlagManager $flagManager */
    private $flagManager;

    /** @var Entries $entries */
    private $entries;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        Config $config,
        QueueStorageInterface $storage,
        PurgeCache $purgeCache,
        LockManagerInterface $lockManager,
        LagDetector $lagDetector,
        FlagManager $flagManager,
        Entries $entries,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->storage = $storage;
        $this->purgeCache = $purgeCache;
        $this->lockManager = $lockManager;
        $this->lagDetector = $lagDetector;
        $this->flagManager = $flagManager;
        $this->entries = $entries;
        $this->logger = $logger;
    }

    /**
     * Releases a batch paced by the `stagger` config; single-shot when disabled.
     */
    public function execute(): void
    {
        if (!$this->config->isStaggerEnabled()) {
            $this->entries->flush();
            return;
        }

        if (!$this->lockManager->lock(self::LOCK_NAME, self::LOCK_TIMEOUT_FAIL_FAST)) {
            $this->logger->debug('[CacheDebounce] Staggered release already running, skipping.');
            return;
        }

        try {
            $this->release();
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    private function release(): void
    {
        $wasResumed = true;
        $batchId = $this->storage->activeBatch();
        if ($batchId === '') {
            $wasResumed = false;
            $batchId = $this->storage->claim();
            if ($batchId === '') {
                $this->logger->debug('[CacheDebounce] Nothing to flush.');
                return;
            }
        }

        // Always re-purges the full batch, even on resume — a BAN is idempotent.
        $tags = $this->storage->tags($batchId);
        $claimedCount = count($tags);
        $chunks = array_chunk($tags, max(1, $this->config->getStaggerBatchSize()));
        $lastChunkIndex = count($chunks) - 1;
        $intervalMicroseconds = max(0, $this->config->getStaggerIntervalMs()) * 1000;
        $maxRuntimeSeconds = $this->config->getStaggerMaxRuntimeSeconds();
        $start = microtime(true);

        $this->config->setShouldDebouncePurgeRequest(false);
        try {
            foreach ($chunks as $index => $chunk) {
                // Budget only checked from chunk 2 on, so a bad config can't block all progress.
                if ($index > 0 && (microtime(true) - $start) >= $maxRuntimeSeconds) {
                    $this->logger->debug(
                        "[CacheDebounce] Staggered release hit its runtime budget; "
                            . "batch $batchId stays claimed for the next run."
                    );
                    return;
                }

                if (!$this->purgeCache->sendPurgeRequest($chunk)) {
                    $this->logger->error(
                        "[CacheDebounce] Staggered purge chunk failed — leaving batch $batchId queued for retry."
                    );
                    return;
                }

                if ($index !== $lastChunkIndex) {
                    usleep($intervalMicroseconds);
                }
            }
        } finally {
            $this->config->setShouldDebouncePurgeRequest(true);
        }

        $this->storage->clear($batchId);

        $this->flagManager->saveFlag(Entries::FLAG_LAST_FLUSH_AT, date('Y-m-d H:i:s'));
        $this->flagManager->saveFlag(Entries::FLAG_LAST_FLUSH_DURATION, round(microtime(true) - $start, 1));

        // Skip on resume — pendingCount() would include pre-run backlog too.
        if (!$wasResumed) {
            $this->lagDetector->recordSample($claimedCount, $this->storage->pendingCount());
        }
    }
}
