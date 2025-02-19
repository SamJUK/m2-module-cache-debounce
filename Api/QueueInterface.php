<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Api;

interface QueueInterface
{
    /**
     * Add new tags to the purge queue
     */
    public function add(array $tags) : void;

    /**
     * Get all tags from the purge queue
     */
    public function get() : array;

    /**
     * Purge all queued tags, and clear the queue
     */
    public function flush() : void;
}
