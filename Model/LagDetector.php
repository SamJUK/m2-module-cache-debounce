<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model;

use Magento\Framework\FlagManager;
use Magento\Framework\Notification\NotifierInterface;
use Psr\Log\LoggerInterface;

class LagDetector
{
    public const FLAG_CONSECUTIVE_LAGGING_RUNS = 'samjuk_cache_debounce_consecutive_lagging_runs';

    /** @var Config $config */
    private $config;

    /** @var FlagManager $flagManager */
    private $flagManager;

    /** @var NotifierInterface $notifier */
    private $notifier;

    /** @var LoggerInterface $logger */
    private $logger;

    public function __construct(
        Config $config,
        FlagManager $flagManager,
        NotifierInterface $notifier,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->flagManager = $flagManager;
        $this->notifier = $notifier;
        $this->logger = $logger;
    }

    /**
     * Tracks consecutive lagging runs, re-notifying every N runs while it lasts.
     */
    public function recordSample(int $claimed, int $arrivedDuring): void
    {
        $isLagging = $claimed > 0
            && ($arrivedDuring / $claimed) >= $this->config->getStaggerLagRatioThreshold();

        if (!$isLagging) {
            $this->flagManager->deleteFlag(self::FLAG_CONSECUTIVE_LAGGING_RUNS);
            return;
        }

        $consecutiveRuns = (int)$this->flagManager->getFlagData(self::FLAG_CONSECUTIVE_LAGGING_RUNS) + 1;
        $this->flagManager->saveFlag(self::FLAG_CONSECUTIVE_LAGGING_RUNS, $consecutiveRuns);

        $alertAfterRuns = $this->config->getStaggerLagAlertAfterRuns();
        if ($alertAfterRuns > 0 && $consecutiveRuns % $alertAfterRuns === 0) {
            $message = sprintf(
                'Purge release is lagging behind ingestion: %d tags arrived while %d were being drained '
                    . '(%d consecutive lagging runs).',
                $arrivedDuring,
                $claimed,
                $consecutiveRuns
            );

            $this->logger->warning("[CacheDebounce] $message");

            // NotifierInterface has no addWarning() — addMajor is the closest severity.
            $this->notifier->addMajor('Cache Debounce: purge release is lagging', $message);
        }
    }
}
