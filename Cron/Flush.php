<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Cron;

use SamJUK\CacheDebounce\Model\Entries as CacheDebouncedEntries;

class Flush
{
    /** @var CacheDebouncedEntries $cacheDebouncedEntries */
    private $cacheDebouncedEntries;

    public function __construct(
        CacheDebouncedEntries $cacheDebouncedEntries
    ) {
        $this->cacheDebouncedEntries = $cacheDebouncedEntries;
    }

    public function execute()
    {
        return $this->cacheDebouncedEntries->flush();
    }
}
