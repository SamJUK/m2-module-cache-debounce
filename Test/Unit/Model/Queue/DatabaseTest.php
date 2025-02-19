<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Unit\Model\Queue;

use PHPUnit\Framework\TestCase;
use SamJUK\CacheDebounce\Model\Queue\Database as DatabaseQueue;

class DatabaseTest extends TestCase
{
    private const CACHE_TAGS = ['cat_c_1', 'cat_c_2', 'cat_c_p_1'];

    private $cacheDebounceConfig;
    private $purgeCacheModel;
    private $connection;
    private $resourceConnection;
    private $loggerInterface;
    private $databaseQueue;
    private $select;

    protected function setUp(): void
    {
        $this->select = $this->createMock(\Magento\Framework\DB\Select::class);
        $this->select->method('from')->willReturn($this->select);
        $this->select->method('distinct')->willReturn($this->select);
        $this->connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $this->connection->method('select')->willReturn($this->select);
        $this->cacheDebounceConfig = $this->createMock(\SamJUK\CacheDebounce\Model\Config::class);
        $this->resourceConnection = $this->createMock(\Magento\Framework\App\ResourceConnection::class);
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->purgeCacheModel = $this->createMock(\Magento\CacheInvalidate\Model\PurgeCache::class);
        $this->loggerInterface = $this->createMock(\Psr\Log\LoggerInterface::class);
        $this->databaseQueue = new DatabaseQueue(
            $this->cacheDebounceConfig,
            $this->purgeCacheModel,
            $this->resourceConnection,
            $this->loggerInterface
        );
    }

    public function testFlushTags()
    {
        $this->connection->method('fetchCol')->willReturn([
            self::CACHE_TAGS
        ]);

        $this->purgeCacheModel->expects($this->once())
            ->method('sendPurgeRequest')
            ->with([self::CACHE_TAGS]);

        $this->databaseQueue->flush();
    }
}
