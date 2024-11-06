<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model;

use SamJUK\CacheDebounce\Model\Config;
use Magento\CacheInvalidate\Model\PurgeCache;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class Entries
{
    private $tableName;
    private $resourceConnection;
    private $purgeCache;
    private $config;
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

    public function add($tags)
    {
        $this->resourceConnection->getConnection()
            ->insertArray($this->tableName, ['tag'], $tags);
    }

    public function get()
    {
        $connection = $this->resourceConnection->getConnection();
        $query = $connection->select()->from($this->tableName, 'tag')->distinct();
        return $connection->fetchCol($query);
    }

    public function flush()
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
