<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Unit\Model\Storage;

use PHPUnit\Framework\TestCase;
use SamJUK\CacheDebounce\Model\Storage\Redis;
use SamJUK\CacheDebounce\Model\Storage\Redis\ConnectionResolver;

class RedisTest extends TestCase
{
    private const KEY_LIVE = 'samjuk_cache_debounce:queue:live';
    private const KEY_BATCH_PREFIX = 'samjuk_cache_debounce:queue:batch:';
    private const KEY_ACTIVE_BATCH = 'samjuk_cache_debounce:queue:active_batch_id';

    private $client;
    private $connectionResolver;
    private $storage;

    protected function setUp(): void
    {
        $this->client = $this->getMockBuilder(\Credis_Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['sAdd', 'rename', 'sMembers', 'del', 'sUnionStore', 'sCard', 'set', 'get'])
            ->getMock();
        $this->connectionResolver = $this->createMock(ConnectionResolver::class);
        $this->connectionResolver->method('getClient')->willReturn($this->client);

        $this->storage = new Redis($this->connectionResolver);
    }

    public function testAddWithEmptyTagsDoesNotTouchConnection()
    {
        $this->client->expects($this->never())->method('sAdd');

        $this->storage->add([]);
    }

    public function testAddSaddsTagsIntoTheLiveSet()
    {
        $this->client->expects($this->once())
            ->method('sAdd')
            ->with(self::KEY_LIVE, 'cat_c_1', 'cat_c_2');

        $this->storage->add(['cat_c_1', 'cat_c_2']);
    }

    public function testClaimReturnsEmptyStringWhenLiveKeyDoesNotExist()
    {
        $this->client->method('rename')->willThrowException(new \CredisException('ERR no such key'));

        $this->assertSame('', $this->storage->claim());
    }

    public function testClaimDoesNotSwallowOtherRedisErrors()
    {
        $this->client->method('rename')->willThrowException(new \CredisException('ERR something else'));

        $this->expectException(\CredisException::class);
        $this->expectExceptionMessage('ERR something else');

        $this->storage->claim();
    }

    public function testClaimRenamesLiveKeyToABatchKeyAndReturnsItsId()
    {
        $this->client->expects($this->once())
            ->method('rename')
            ->with(self::KEY_LIVE, $this->callback(function (string $key) {
                return strpos($key, self::KEY_BATCH_PREFIX) === 0
                    && strlen($key) === strlen(self::KEY_BATCH_PREFIX) + 32;
            }));

        $batchId = $this->storage->claim();

        $this->assertNotSame('', $batchId);
        $this->assertSame(32, strlen($batchId));
    }

    public function testClaimRecordsTheActiveBatchMarker()
    {
        $this->client->expects($this->once())
            ->method('set')
            ->with(self::KEY_ACTIVE_BATCH, $this->isType('string'));

        $this->storage->claim();
    }

    public function testClaimDoesNotSetTheActiveBatchMarkerWhenNothingWasPending()
    {
        $this->client->method('rename')->willThrowException(new \CredisException('ERR no such key'));
        $this->client->expects($this->never())->method('set');

        $this->storage->claim();
    }

    public function testTagsReadsFromTheBatchKey()
    {
        $this->client->expects($this->once())
            ->method('sMembers')
            ->with(self::KEY_BATCH_PREFIX . 'batch-123')
            ->willReturn(['cat_c_1']);

        $this->assertSame(['cat_c_1'], $this->storage->tags('batch-123'));
    }

    public function testClearDeletesTheBatchKeyAndTheActiveBatchMarker()
    {
        $deletedKeys = [];
        $this->client->expects($this->exactly(2))
            ->method('del')
            ->willReturnCallback(function (string $key) use (&$deletedKeys) {
                $deletedKeys[] = $key;
                return true;
            });

        $this->storage->clear('batch-123');

        $this->assertSame([self::KEY_BATCH_PREFIX . 'batch-123', self::KEY_ACTIVE_BATCH], $deletedKeys);
    }

    public function testReleaseMergesBatchSetBackIntoLiveSetThenDeletesTheBatchAndMarker()
    {
        $this->client->expects($this->once())
            ->method('sUnionStore')
            ->with(self::KEY_LIVE, self::KEY_LIVE, self::KEY_BATCH_PREFIX . 'batch-123');

        $deletedKeys = [];
        $this->client->expects($this->exactly(2))
            ->method('del')
            ->willReturnCallback(function (string $key) use (&$deletedKeys) {
                $deletedKeys[] = $key;
                return true;
            });

        $this->storage->release('batch-123');

        $this->assertSame([self::KEY_BATCH_PREFIX . 'batch-123', self::KEY_ACTIVE_BATCH], $deletedKeys);
    }

    public function testPendingCountReadsCardinalityOfTheLiveSet()
    {
        $this->client->expects($this->once())
            ->method('sCard')
            ->with(self::KEY_LIVE)
            ->willReturn(3);

        $this->assertSame(3, $this->storage->pendingCount());
    }

    public function testActiveBatchReturnsEmptyStringWhenNoBatchIsClaimed()
    {
        $this->client->method('get')->with(self::KEY_ACTIVE_BATCH)->willReturn(false);

        $this->assertSame('', $this->storage->activeBatch());
    }

    public function testActiveBatchReturnsIdOfAlreadyClaimedBatch()
    {
        $this->client->method('get')->with(self::KEY_ACTIVE_BATCH)->willReturn('batch-123');

        $this->assertSame('batch-123', $this->storage->activeBatch());
    }

    public function testOldestPendingAgeSecondsIsAlwaysNull()
    {
        $this->assertNull($this->storage->oldestPendingAgeSeconds());
    }
}
