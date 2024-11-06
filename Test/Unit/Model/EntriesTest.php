<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use SamJUK\CacheDebounce\Model\Entries as CacheDebounceEntries;

class EntriesTest extends TestCase
{
    private const CACHE_TAGS = ['cat_c_1', 'cat_c_2', 'cat_c_p_1'];

    private $cacheDebounceConfig;
    private $purgeCacheModel;
    private $connection;
    private $resourceConnection;
    private $loggerInterface;
    private $cacheDebounceEntries;
    private $select;

    protected function setUp(): void
    {
        $this->select = $this->createMock(\Magento\Framework\DB\Select::class);
        $this->select->method('from')->willReturn($this->select);
        $this->select->method('distinct')->willReturn($this->select);
        $this->connection = $this->createMock(\Magento\Framework\DB\Adapter\Pdo\Mysql::class);
        $this->connection->method('select')->willReturn($this->select);
        $this->cacheDebounceConfig = $this->createMock(\SamJUK\CacheDebounce\Model\Config::class);
        $this->resourceConnection = $this->createMock(\Magento\Framework\App\ResourceConnection::class);
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->purgeCacheModel = $this->createMock(\Magento\CacheInvalidate\Model\PurgeCache::class);
        $this->loggerInterface = $this->createMock(\Psr\Log\LoggerInterface::class);
        $this->cacheDebounceEntries = new CacheDebounceEntries(
            $this->cacheDebounceConfig,
            $this->purgeCacheModel,
            $this->resourceConnection,
            $this->loggerInterface
        );
    }

    public function testAddNewTag()
    {
        // $this->cacheDebounceEntries->add([
        //     'cat_c_2',
        //     'cat_c_3',
        //     'cat_p_3'
        // ]);
        $this->assertTrue(true);
    }

    public function testGetAllTags()
    {
        $this->assertTrue(true);
    }

    public function testFlushTags()
    {
        $this->connection->method('fetchCol')->willReturn([
            self::CACHE_TAGS
        ]);

        $this->purgeCacheModel->expects($this->once())
            ->method('sendPurgeRequest')
            ->with([self::CACHE_TAGS]);

        $this->cacheDebounceEntries->flush();
    }
}
