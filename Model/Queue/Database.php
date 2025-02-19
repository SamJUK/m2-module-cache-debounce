<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model\Queue;

use SamJUK\CacheDebounce\Api\QueueInterface;
use SamJUK\CacheDebounce\Model\Config;
use Magento\CacheInvalidate\Model\PurgeCache;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class Database implements QueueInterface
{
    /** @var string $tableName */
    private $tableName;

    /** @var ResourceConnection $resourceConnection */
    private $resourceConnection;

    /** @var PurgeCache $purgeCache */
    private $purgeCache;

    /** @var Config $config */
    private $config;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        Config $config,
        PurgeCache $purgeCache,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->purgeCache = $purgeCache;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
        $this->tableName = $resourceConnection->getTableName('samjuk_cache_debounce');
    }

    /**
     * {@inheritDoc}
     */
    public function add(array $tags) : void
    {
        $this->resourceConnection->getConnection()
            ->insertArray($this->tableName, ['tag'], $tags);
    }

    /**
     * {@inheritDoc}
     */
    public function get() : array
    {
        $connection = $this->resourceConnection->getConnection();
        $query = $connection->select()->from($this->tableName, 'tag')->distinct();
        return $connection->fetchCol($query);
    }

    /**
     * {@inheritDoc}
     */
    public function flush() : void
    {
        $tags = $this->get();
        if (count($tags) > 0) {
            $this->logger->debug("[CacheDebounce] Flushing Tags: " . json_encode($tags));
            $this->config->setShouldDebouncePurgeRequest(false);
            $this->purgeCache->sendPurgeRequest($tags);
            $this->resourceConnection->getConnection()->delete($this->tableName);
        } else {
            $this->logger->debug("[CacheDebounce] Nothing to flush");
        }
    }
}
