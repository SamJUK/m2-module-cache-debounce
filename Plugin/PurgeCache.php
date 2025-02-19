<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Plugin;

use SamJUK\CacheDebounce\Model\Config;
use SamJUK\CacheDebounce\Model\Entries as CacheDebounceEntries;
use Magento\CacheInvalidate\Model\PurgeCache as Subject;

class PurgeCache
{
    /** @var Config $config **/
    private $config;

    /** @var CacheDebounceEntries $cacheDebouncedEntries */
    private $cacheDebouncedEntries;

    public function __construct(
        Config $config,
        CacheDebounceEntries $cacheDebounceEntries
    ) {
        $this->config = $config;
        $this->cacheDebouncedEntries = $cacheDebounceEntries;
    }

    /**
     * Prevent flushing varnish now, and push the tag into a queue to flush later, if required.
     *
     * @param \Magento\CacheInvalidate\Model\PurgeCache $subject
     * @param callable $proceed
     * @param array|string $tags
     * @return bool
     */
    public function aroundSendPurgeRequest(Subject $subject, callable $proceed, $tags) : bool
    {
        if (!$this->config->shouldDebouncePurgeRequest()) {
            return $proceed($tags);
        }

        if (is_string($tags)) {
            $tags = [$tags];
        }

        $this->cacheDebouncedEntries->add($tags);
        return true;
    }
}
