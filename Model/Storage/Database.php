<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model\Storage;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class Database implements QueueStorageInterface
{
    private const UNCLAIMED_BATCH_ID = '';

    /** @var string $tableName */
    private $tableName;

    /** @var ResourceConnection $resourceConnection */
    private $resourceConnection;

    public function __construct(
        ResourceConnection $resourceConnection
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->tableName = $resourceConnection->getTableName('samjuk_cache_debounce');
    }

    /**
     * @inheritDoc
     *
     * Dedupes against pending tags at the app level; no PK for this yet.
     */
    public function add(array $tags): void
    {
        $tags = array_values(array_unique(array_filter($tags, fn ($tag) => $tag !== null && $tag !== '')));
        if (!$tags) {
            return;
        }

        $newTags = array_diff($tags, $this->tags(self::UNCLAIMED_BATCH_ID));
        if (!$newTags) {
            return;
        }

        $rows = [];
        foreach ($newTags as $tag) {
            $rows[] = [self::UNCLAIMED_BATCH_ID, $tag];
        }

        $this->resourceConnection->getConnection()->insertArray(
            $this->tableName,
            ['batch_id', 'tag'],
            $rows,
            AdapterInterface::INSERT_IGNORE
        );
    }

    /**
     * @inheritDoc
     */
    public function claim(): string
    {
        $batchId = bin2hex(random_bytes(16));

        $affectedRows = $this->resourceConnection->getConnection()->update(
            $this->tableName,
            ['batch_id' => $batchId],
            $this->quoteBatchId(self::UNCLAIMED_BATCH_ID)
        );

        return $affectedRows > 0 ? $batchId : '';
    }

    /**
     * @inheritDoc
     */
    public function tags(string $batchId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $query = $connection->select()
            ->from($this->tableName, 'tag')
            ->where('batch_id = ?', $batchId);

        return $connection->fetchCol($query);
    }

    /**
     * @inheritDoc
     */
    public function clear(string $batchId): void
    {
        $this->resourceConnection->getConnection()->delete(
            $this->tableName,
            $this->quoteBatchId($batchId)
        );
    }

    /**
     * @inheritDoc
     */
    public function release(string $batchId): void
    {
        $this->add($this->tags($batchId));
        $this->clear($batchId);
    }

    private function quoteBatchId(string $batchId): string
    {
        return $this->resourceConnection->getConnection()->quoteInto('batch_id = ?', $batchId);
    }
}
