<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Unit\Model\Storage;

use PHPUnit\Framework\TestCase;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use SamJUK\CacheDebounce\Model\Storage\Database;
use SamJUK\CacheDebounce\Model\Storage\Pool;
use SamJUK\CacheDebounce\Model\Storage\Redis;

class PoolTest extends TestCase
{
    private const XML_PATH_STORAGE_DRIVER = 'samjuk_cache_debounce_advanced/general/storage_driver';
    private const CACHE_TAGS = ['cat_c_1', 'cat_c_2'];
    private const DRIVERS = [
        'db' => Database::class,
        'redis' => Redis::class,
    ];

    private $objectManager;
    private $scopeConfig;
    private $database;
    private $redis;

    protected function setUp(): void
    {
        $this->objectManager = $this->createMock(ObjectManagerInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->database = $this->createMock(Database::class);
        $this->redis = $this->createMock(Redis::class);

        $this->objectManager->method('create')->willReturnMap([
            [Database::class, [], $this->database],
            [Redis::class, [], $this->redis],
        ]);
    }

    private function pool(): Pool
    {
        return new Pool($this->objectManager, $this->scopeConfig, self::DRIVERS);
    }

    public function testUsesDatabaseDriverByDefault()
    {
        $this->scopeConfig->method('getValue')->with(self::XML_PATH_STORAGE_DRIVER)->willReturn(null);

        $this->database->expects($this->once())->method('add')->with(self::CACHE_TAGS);

        $this->pool()->add(self::CACHE_TAGS);
    }

    public function testUsesDatabaseDriverForAnyValueOtherThanRedis()
    {
        $this->scopeConfig->method('getValue')->willReturn('db');

        $this->database->expects($this->once())->method('claim')->willReturn('batch-123');

        $this->pool()->claim();
    }

    public function testUsesRedisDriverWhenConfiguredAndNeverTouchesDatabase()
    {
        $this->scopeConfig->method('getValue')->willReturn('redis');

        $this->database->expects($this->never())->method('add');
        $this->redis->expects($this->once())->method('add')->with(self::CACHE_TAGS);

        $this->pool()->add(self::CACHE_TAGS);
    }

    public function testDriverIsOnlyResolvedOnceAcrossMultipleCalls()
    {
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->once())->method('create')->with(Redis::class)->willReturn($this->redis);
        $this->scopeConfig->method('getValue')->willReturn('redis');

        $pool = new Pool($objectManager, $this->scopeConfig, self::DRIVERS);
        $pool->add(['cat_c_1']);
        $pool->claim();
    }

    public function testReleaseDelegatesToTheResolvedDriver()
    {
        $this->scopeConfig->method('getValue')->willReturn('db');
        $this->database->expects($this->once())->method('release')->with('batch-123');

        $this->pool()->release('batch-123');
    }

    public function testActiveBatchDelegatesToTheResolvedDriver()
    {
        $this->scopeConfig->method('getValue')->willReturn('redis');
        $this->redis->expects($this->once())->method('activeBatch')->willReturn('batch-123');

        $this->assertSame('batch-123', $this->pool()->activeBatch());
    }
}
