<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Clears disposable queue state before a follow-up release adds a PK.
 */
class ClearQueueTable implements DataPatchInterface
{
    /** @var ModuleDataSetupInterface $moduleDataSetup */
    private $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): void
    {
        // DELETE not TRUNCATE: data patches run inside a transaction.
        $connection = $this->moduleDataSetup->getConnection();
        $connection->delete($this->moduleDataSetup->getTable('samjuk_cache_debounce'));
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
