<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Integration\Model;

use PHPUnit\Framework\TestCase;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\ObjectManager;
use SamJUK\CacheDebounce\Model\Entries;
use SamJUK\CacheDebounce\Model\Storage\QueueStorageInterface;

class EntriesTest extends TestCase
{
    private const CACHE_TAGS = ['cat_c_1', 'cat_c_2', 'cat_c_p_1'];

    protected $objectManager;
    private $cacheDebouncedEntries;
    private $storage;
    private $resourceConnection;

    protected function setUp(): void
    {
        $this->objectManager = ObjectManager::getInstance();
        $this->cacheDebouncedEntries = $this->objectManager->get(Entries::class);
        $this->storage = $this->objectManager->get(QueueStorageInterface::class);

        // Independent of the classes under test, so a prior test failing
        // mid-assertion can never leak rows into the next test.
        $this->resourceConnection = $this->objectManager->get(ResourceConnection::class);
        $this->truncateTable();
    }

    protected function tearDown(): void
    {
        $this->truncateTable();
    }

    private function truncateTable(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->delete($this->resourceConnection->getTableName('samjuk_cache_debounce'));
    }

    public function testDebouncedEntriesStorage()
    {
        $this->cacheDebouncedEntries->add(self::CACHE_TAGS);

        $batchId = $this->storage->claim();
        $this->assertEquals(self::CACHE_TAGS, $this->storage->tags($batchId));

        $this->storage->clear($batchId);
        $this->assertEquals([], $this->storage->tags($batchId));
    }

    /**
     * Regression test for the add-during-purge race: a tag queued after a
     * batch has been claimed must survive into the *next* batch, not be
     * silently dropped by the trailing clear() of the batch currently
     * being purged.
     */
    public function testTagAddedWhileBatchIsClaimedSurvivesIntoNextBatch()
    {
        $this->cacheDebouncedEntries->add(['cat_c_1']);

        $batchId = $this->storage->claim();

        $this->cacheDebouncedEntries->add(['cat_c_2']);

        $this->assertEquals(['cat_c_1'], $this->storage->tags($batchId));

        $this->storage->clear($batchId);

        $nextBatchId = $this->storage->claim();
        $this->assertEquals(['cat_c_2'], $this->storage->tags($nextBatchId));
    }

    public function testSecondConcurrentClaimReturnsEmptyString()
    {
        $this->cacheDebouncedEntries->add(self::CACHE_TAGS);

        $batchId = $this->storage->claim();
        $this->assertNotSame('', $batchId);

        $this->assertSame('', $this->storage->claim());

        $this->storage->clear($batchId);
    }
}
