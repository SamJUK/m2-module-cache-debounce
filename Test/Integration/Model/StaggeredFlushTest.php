<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Integration\Model;

use PHPUnit\Framework\TestCase;
use Magento\CacheInvalidate\Model\PurgeCache;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\TestFramework\ObjectManager;
use SamJUK\CacheDebounce\Model\Entries;
use SamJUK\CacheDebounce\Model\StaggeredFlush;
use SamJUK\CacheDebounce\Model\Storage\QueueStorageInterface;

/**
 * @magentoConfigFixture samjuk_cache_debounce/stagger/enabled 1
 * @magentoConfigFixture samjuk_cache_debounce/stagger/batch_size 1
 * @magentoConfigFixture samjuk_cache_debounce/stagger/interval_ms 0
 * @magentoConfigFixture samjuk_cache_debounce/stagger/max_runtime_seconds 240
 */
class StaggeredFlushTest extends TestCase
{
    private const LOCK_NAME = 'samjuk_cache_debounce_flush';

    protected $objectManager;
    private $storage;
    private $entries;
    private $resourceConnection;

    protected function setUp(): void
    {
        $this->objectManager = ObjectManager::getInstance();
        $this->storage = $this->objectManager->get(QueueStorageInterface::class);
        $this->entries = $this->objectManager->get(Entries::class);

        // Independent of the classes under test, so a prior test failing
        // mid-assertion can never leak rows into the next test.
        $this->resourceConnection = $this->objectManager->get(ResourceConnection::class);
        $this->truncateTable();
    }

    protected function tearDown(): void
    {
        $this->truncateTable();
        $this->objectManager->removeSharedInstance(PurgeCache::class);
    }

    private function truncateTable(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->delete($this->resourceConnection->getTableName('samjuk_cache_debounce'));
    }

    /**
     * Double must be shared-instanced before create() resolves it.
     */
    private function staggeredFlushWithPurgeCache(PurgeCache $purgeCache): StaggeredFlush
    {
        $this->objectManager->addSharedInstance($purgeCache, PurgeCache::class);

        return $this->objectManager->create(StaggeredFlush::class);
    }

    public function testInterruptedRunLeavesBatchClaimedAndTheNextRunResumesAndDrainsIt()
    {
        $this->entries->add(['cat_c_1', 'cat_c_2', 'cat_c_3']);

        $calls = 0;
        $crashingPurgeCache = $this->createMock(PurgeCache::class);
        $crashingPurgeCache->method('sendPurgeRequest')->willReturnCallback(function ($tags) use (&$calls) {
            $calls++;
            if ($calls > 2) {
                throw new \RuntimeException('simulated crash mid-loop');
            }
            return true;
        });

        try {
            $this->staggeredFlushWithPurgeCache($crashingPurgeCache)->execute();
            $this->fail('Expected the simulated crash to propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated crash mid-loop', $e->getMessage());
        }

        $batchId = $this->storage->activeBatch();
        $this->assertNotSame('', $batchId, 'An interrupted batch must stay claimed for the next run.');

        $healthyPurgeCache = $this->createMock(PurgeCache::class);
        $healthyPurgeCache->method('sendPurgeRequest')->willReturn(true);

        $this->staggeredFlushWithPurgeCache($healthyPurgeCache)->execute();

        $this->assertSame('', $this->storage->activeBatch());
        $this->assertSame(0, $this->storage->pendingCount());
    }

    public function testConcurrentInvocationsOnlyOneProceedsPastTheLock()
    {
        $this->entries->add(['cat_c_1']);

        $purgeCache = $this->createMock(PurgeCache::class);
        $purgeCache->method('sendPurgeRequest')->willReturn(true);
        $staggeredFlush = $this->staggeredFlushWithPurgeCache($purgeCache);

        $lockManager = $this->objectManager->get(LockManagerInterface::class);
        $this->assertTrue($lockManager->lock(self::LOCK_NAME, 0));

        try {
            $staggeredFlush->execute();

            // Lock held by us — the concurrent invocation must have backed
            // off without touching the queue at all.
            $this->assertSame(1, $this->storage->pendingCount());
            $this->assertSame('', $this->storage->activeBatch());
        } finally {
            $lockManager->unlock(self::LOCK_NAME);
        }

        // Lock released — the next invocation proceeds and drains normally.
        $staggeredFlush->execute();
        $this->assertSame(0, $this->storage->pendingCount());
        $this->assertSame('', $this->storage->activeBatch());
    }
}
