<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Cron;

use SamJUK\CacheDebounce\Api\QueueInterface;

class Flush
{
    /** @var QueueInterface $queue */
    private $queue;

    public function __construct(
        QueueInterface $queue
    ) {
        $this->queue = $queue;
    }

    public function execute() : void
    {
        $this->queue->flush();
    }
}
