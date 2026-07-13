<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Console\Command;

use Magento\Framework\FlagManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use SamJUK\CacheDebounce\Model\Config;
use SamJUK\CacheDebounce\Model\Entries;
use SamJUK\CacheDebounce\Model\LagDetector;
use SamJUK\CacheDebounce\Model\Storage\QueueStorageInterface;

class Status extends Command
{
    private $storage;
    private $config;
    private $flagManager;

    /**
     * @param QueueStorageInterface $storage
     * @param Config $config
     * @param FlagManager $flagManager
     * @param string|null $name
     */
    public function __construct(
        QueueStorageInterface $storage,
        Config $config,
        FlagManager $flagManager,
        $name = null
    ) {
        $this->storage = $storage;
        $this->config = $config;
        $this->flagManager = $flagManager;
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('samjuk:cache-debounce:status');
        $this->setDescription('Shows the current state of the debounced purge queue.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pending = $this->storage->pendingCount();
        $oldestAgeSeconds = $this->storage->oldestPendingAgeSeconds();
        $activeBatch = $this->storage->activeBatch();
        $activeBatchTagCount = $activeBatch !== '' ? count($this->storage->tags($activeBatch)) : 0;

        $lastFlushAt = $this->flagManager->getFlagData(Entries::FLAG_LAST_FLUSH_AT);
        $lastFlushDuration = $this->flagManager->getFlagData(Entries::FLAG_LAST_FLUSH_DURATION);
        $consecutiveLaggingRuns = (int)$this->flagManager->getFlagData(LagDetector::FLAG_CONSECUTIVE_LAGGING_RUNS);

        $oldestAgeLabel = $oldestAgeSeconds !== null
            ? sprintf(' (oldest queued %s ago)', $this->formatDuration($oldestAgeSeconds))
            : '';

        $output->writeln(sprintf('Pending tags:             %d%s', $pending, $oldestAgeLabel));

        $output->writeln(sprintf(
            'Active batch:             %s',
            $activeBatch === '' ? 'none' : sprintf('%s (%d tags)', $activeBatch, $activeBatchTagCount)
        ));

        $output->writeln(sprintf(
            'Last flush:               %s',
            $lastFlushAt ? sprintf('%s (duration %.1fs)', $lastFlushAt, (float)$lastFlushDuration) : 'never'
        ));

        $output->writeln(sprintf(
            'Consecutive lagging runs: %d / %d',
            $consecutiveLaggingRuns,
            $this->config->getStaggerLagAlertAfterRuns()
        ));

        return 0;
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        return sprintf('%dm%02ds', intdiv($seconds, 60), $seconds % 60);
    }
}
