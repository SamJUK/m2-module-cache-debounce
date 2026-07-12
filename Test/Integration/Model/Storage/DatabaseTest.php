<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Integration\Model\Storage;

use PHPUnit\Framework\TestCase;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\ObjectManager;
use SamJUK\CacheDebounce\Model\Storage\Database;

class DatabaseTest extends TestCase
{
    protected $objectManager;
    private $storage;
    private $resourceConnection;

    protected function setUp(): void
    {
        $this->objectManager = ObjectManager::getInstance();
        $this->storage = $this->objectManager->create(Database::class);

        // Independent of the class under test, so a prior test failing
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

    public function testAddClaimTagsClearRoundTrip()
    {
        $this->storage->add(['cat_c_1', 'cat_c_2']);

        $batchId = $this->storage->claim();
        $this->assertNotSame('', $batchId);

        $this->assertEquals(['cat_c_1', 'cat_c_2'], $this->storage->tags($batchId));

        $this->storage->clear($batchId);
        $this->assertEquals([], $this->storage->tags($batchId));
    }

    public function testAddDuringClaimIsNotLostOrDuplicated()
    {
        $this->storage->add(['cat_c_1']);

        $batchId = $this->storage->claim();

        // Overlapping tag added after the claim snapshot — must land in the
        // next batch, not be silently merged into the one already claimed.
        $this->storage->add(['cat_c_1', 'cat_c_2']);

        $this->assertEquals(['cat_c_1'], $this->storage->tags($batchId));

        $this->storage->clear($batchId);

        $nextBatchId = $this->storage->claim();
        $this->assertEquals(['cat_c_1', 'cat_c_2'], $this->storage->tags($nextBatchId));
    }

    public function testAddDoesNotDuplicateAlreadyPendingTags()
    {
        $this->storage->add(['cat_c_1']);
        $this->storage->add(['cat_c_1', 'cat_c_2']);

        $batchId = $this->storage->claim();

        $this->assertEquals(['cat_c_1', 'cat_c_2'], $this->storage->tags($batchId));
    }

    public function testAddIgnoresNullAndEmptyTags()
    {
        $this->storage->add(['cat_c_1', null, '']);

        $batchId = $this->storage->claim();

        $this->assertEquals(['cat_c_1'], $this->storage->tags($batchId));
    }

    public function testSecondConcurrentClaimReturnsEmptyString()
    {
        $this->storage->add(['cat_c_1']);

        $firstBatchId = $this->storage->claim();
        $this->assertNotSame('', $firstBatchId);

        $this->assertSame('', $this->storage->claim());

        $this->storage->clear($firstBatchId);
    }

    public function testReleaseMakesTheBatchClaimableAgain()
    {
        $this->storage->add(['cat_c_1', 'cat_c_2']);
        $batchId = $this->storage->claim();

        $this->storage->release($batchId);

        $this->assertEquals([], $this->storage->tags($batchId));

        $nextBatchId = $this->storage->claim();
        $this->assertEquals(['cat_c_1', 'cat_c_2'], $this->storage->tags($nextBatchId));
    }

    public function testReleaseMergesCleanlyWithTagsReQueuedWhileClaimed()
    {
        $this->storage->add(['cat_c_1']);
        $batchId = $this->storage->claim();

        // Same tag re-queued while the original batch is still in flight —
        // release() must not end up with two pending rows for it.
        $this->storage->add(['cat_c_1']);

        $this->storage->release($batchId);

        $nextBatchId = $this->storage->claim();
        $this->assertEquals(['cat_c_1'], $this->storage->tags($nextBatchId));
    }
}
