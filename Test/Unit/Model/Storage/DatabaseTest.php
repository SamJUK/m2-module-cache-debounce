<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Unit\Model\Storage;

use PHPUnit\Framework\TestCase;
use SamJUK\CacheDebounce\Model\Storage\Database;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class DatabaseTest extends TestCase
{
    private const TABLE_NAME = 'samjuk_cache_debounce';

    private $connection;
    private $resourceConnection;
    private $storage;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('quoteInto')->willReturnCallback(
            fn ($text, $value) => str_replace('?', "'$value'", $text)
        );
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')->willReturn(self::TABLE_NAME);

        $this->storage = new Database($this->resourceConnection);
    }

    private function stubSelect(): void
    {
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturn($select);
        $select->method('where')->willReturn($select);
        $this->connection->method('select')->willReturn($select);
    }

    public function testAddWithEmptyTagsDoesNotTouchConnection()
    {
        $this->connection->expects($this->never())->method('insertArray');

        $this->storage->add([]);
    }

    public function testAddInsertsTagsAsUnclaimedWithInsertIgnore()
    {
        $this->stubSelect();
        $this->connection->method('fetchCol')->willReturn([]);

        $this->connection->expects($this->once())
            ->method('insertArray')
            ->with(
                self::TABLE_NAME,
                ['batch_id', 'tag'],
                [['', 'cat_c_1'], ['', 'cat_c_2']],
                AdapterInterface::INSERT_IGNORE
            );

        $this->storage->add(['cat_c_1', 'cat_c_2']);
    }

    public function testAddFiltersOutNullAndEmptyTags()
    {
        $this->stubSelect();
        $this->connection->method('fetchCol')->willReturn([]);

        $this->connection->expects($this->once())
            ->method('insertArray')
            ->with(
                self::TABLE_NAME,
                ['batch_id', 'tag'],
                [['', 'cat_c_1']],
                AdapterInterface::INSERT_IGNORE
            );

        $this->storage->add(['cat_c_1', null, '']);
    }

    public function testAddSkipsTagsAlreadyPending()
    {
        $this->stubSelect();
        $this->connection->method('fetchCol')->willReturn(['cat_c_1']);

        $this->connection->expects($this->once())
            ->method('insertArray')
            ->with(
                self::TABLE_NAME,
                ['batch_id', 'tag'],
                [['', 'cat_c_2']],
                AdapterInterface::INSERT_IGNORE
            );

        $this->storage->add(['cat_c_1', 'cat_c_2']);
    }

    public function testAddInsertsNothingWhenEveryTagIsAlreadyPending()
    {
        $this->stubSelect();
        $this->connection->method('fetchCol')->willReturn(['cat_c_1']);

        $this->connection->expects($this->never())->method('insertArray');

        $this->storage->add(['cat_c_1']);
    }

    public function testClaimReturnsEmptyStringWhenNothingPending()
    {
        $this->connection->method('update')->willReturn(0);

        $this->assertSame('', $this->storage->claim());
    }

    public function testClaimReturnsBatchIdWhenRowsClaimed()
    {
        $this->connection->method('update')->willReturn(2);

        $batchId = $this->storage->claim();

        $this->assertNotSame('', $batchId);
        $this->assertSame(32, strlen($batchId));
    }

    public function testTagsSelectsScopedToBatchId()
    {
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturn($select);
        $select->method('where')->with('batch_id = ?', 'batch-123')->willReturn($select);
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchCol')->willReturn(['cat_c_1']);

        $this->assertSame(['cat_c_1'], $this->storage->tags('batch-123'));
    }

    public function testClearDeletesOnlyThatBatch()
    {
        $this->connection->expects($this->once())
            ->method('delete')
            ->with(self::TABLE_NAME, "batch_id = 'batch-123'");

        $this->storage->clear('batch-123');
    }

    public function testReleaseReQueuesBatchTagsAsUnclaimedThenClearsTheBatch()
    {
        $this->stubSelect();
        // First call is tags($batchId) reading the claimed batch; second is
        // add()'s own dedup check against currently-pending tags.
        $this->connection->method('fetchCol')->willReturnOnConsecutiveCalls(['cat_c_1', 'cat_c_2'], []);

        $this->connection->expects($this->once())
            ->method('insertArray')
            ->with(
                self::TABLE_NAME,
                ['batch_id', 'tag'],
                [['', 'cat_c_1'], ['', 'cat_c_2']],
                AdapterInterface::INSERT_IGNORE
            );
        $this->connection->expects($this->once())
            ->method('delete')
            ->with(self::TABLE_NAME, "batch_id = 'batch-123'");

        $this->storage->release('batch-123');
    }

    public function testActiveBatchReturnsEmptyStringWhenNothingClaimed()
    {
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturn($select);
        $select->method('where')->with('batch_id != ?', '')->willReturn($select);
        $select->method('limit')->with(1)->willReturn($select);
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchOne')->willReturn(false);

        $this->assertSame('', $this->storage->activeBatch());
    }

    public function testActiveBatchReturnsIdOfAlreadyClaimedBatch()
    {
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturn($select);
        $select->method('where')->willReturn($select);
        $select->method('limit')->willReturn($select);
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchOne')->willReturn('batch-123');

        $this->assertSame('batch-123', $this->storage->activeBatch());
    }
}
