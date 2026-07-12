<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Unit\Model\Storage;

use PHPUnit\Framework\TestCase;
use SamJUK\CacheDebounce\Model\Storage\Redis;
use SamJUK\CacheDebounce\Model\Storage\Redis\ConnectionResolver;

class RedisTest extends TestCase
{
    private const KEY_LIVE = 'samjuk_cache_debounce:queue:live';
    private const KEY_BATCH_PREFIX = 'samjuk_cache_debounce:queue:batch:';
    private const ACTIVE_BATCH_ID = 'active';

    private $client;
    private $connectionResolver;
    private $storage;

    protected function setUp(): void
    {
        $this->client = $this->getMockBuilder(\Credis_Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['sAdd', 'rename', 'sMembers', 'del', 'sUnionStore', 'sCard'])
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
        $this->client->method('sCard')->willReturn(0);
        $this->client->method('rename')->willThrowException(new \CredisException('ERR no such key'));

        $this->assertSame('', $this->storage->claim());
    }

    public function testClaimDoesNotSwallowOtherRedisErrors()
    {
        $this->client->method('sCard')->willReturn(0);
        $this->client->method('rename')->willThrowException(new \CredisException('ERR something else'));

        $this->expectException(\CredisException::class);
        $this->expectExceptionMessage('ERR something else');

        $this->storage->claim();
    }

    public function testClaimRenamesLiveKeyToABatchKeyAndReturnsItsId()
    {
        $this->client->method('sCard')->willReturn(0);
        $this->client->expects($this->once())
            ->method('rename')
            ->with(self::KEY_LIVE, self::KEY_BATCH_PREFIX . self::ACTIVE_BATCH_ID);

        $batchId = $this->storage->claim();

        $this->assertSame(self::ACTIVE_BATCH_ID, $batchId);
    }

    public function testClaimReturnsEmptyStringWhenAnotherBatchIsAlreadyClaimed()
    {
        $this->client->method('sCard')->willReturn(1);
        $this->client->expects($this->never())->method('rename');

        $this->assertSame('', $this->storage->claim());
    }

    public function testTagsReadsFromTheBatchKey()
    {
        $this->client->expects($this->once())
            ->method('sMembers')
            ->with(self::KEY_BATCH_PREFIX . 'batch-123')
            ->willReturn(['cat_c_1']);

        $this->assertSame(['cat_c_1'], $this->storage->tags('batch-123'));
    }

    public function testClearDeletesTheBatchKey()
    {
        $this->client->expects($this->once())
            ->method('del')
            ->with(self::KEY_BATCH_PREFIX . self::ACTIVE_BATCH_ID);

        $this->storage->clear(self::ACTIVE_BATCH_ID);
    }

    public function testReleaseMergesBatchSetBackIntoLiveSetThenDeletesTheBatch()
    {
        $this->client->expects($this->once())
            ->method('sUnionStore')
            ->with(self::KEY_LIVE, self::KEY_LIVE, self::KEY_BATCH_PREFIX . self::ACTIVE_BATCH_ID);
        $this->client->expects($this->once())
            ->method('del')
            ->with(self::KEY_BATCH_PREFIX . self::ACTIVE_BATCH_ID);

        $this->storage->release(self::ACTIVE_BATCH_ID);
    }

    public function testActiveBatchReturnsEmptyStringWhenNoBatchIsClaimed()
    {
        $this->client->method('sCard')->with(self::KEY_BATCH_PREFIX . self::ACTIVE_BATCH_ID)->willReturn(0);

        $this->assertSame('', $this->storage->activeBatch());
    }

    public function testActiveBatchReturnsIdOfAlreadyClaimedBatch()
    {
        $this->client->method('sCard')->with(self::KEY_BATCH_PREFIX . self::ACTIVE_BATCH_ID)->willReturn(1);

        $this->assertSame(self::ACTIVE_BATCH_ID, $this->storage->activeBatch());
    }
}
