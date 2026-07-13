<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Cron;

use SamJUK\CacheDebounce\Model\StaggeredFlush;

class Flush
{
    /** @var StaggeredFlush $staggeredFlush */
    private $staggeredFlush;

    public function __construct(
        StaggeredFlush $staggeredFlush
    ) {
        $this->staggeredFlush = $staggeredFlush;
    }

    public function execute() : void
    {
        $this->staggeredFlush->execute();
    }
}
