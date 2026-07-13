<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    private const XML_PATH_GENERAL_ENABLED = 'samjuk_cache_debounce/general/enabled';
    private const XML_PATH_STAGGER_ENABLED = 'samjuk_cache_debounce/stagger/enabled';
    private const XML_PATH_STAGGER_BATCH_SIZE = 'samjuk_cache_debounce/stagger/batch_size';
    private const XML_PATH_STAGGER_INTERVAL_MS = 'samjuk_cache_debounce/stagger/interval_ms';
    private const XML_PATH_STAGGER_MAX_RUNTIME_SECONDS = 'samjuk_cache_debounce/stagger/max_runtime_seconds';
    private const XML_PATH_STAGGER_LAG_RATIO_THRESHOLD = 'samjuk_cache_debounce/stagger/lag_ratio_threshold';
    private const XML_PATH_STAGGER_LAG_ALERT_AFTER_RUNS = 'samjuk_cache_debounce/stagger/lag_alert_after_runs';

    /** @var bool $shouldDebouncePurgeRequest */
    private $shouldDebouncePurgeRequest = true;

    /** @var ScopeConfigInterface $scopeConfig */
    private $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if the module feature flag is enabled
     */
    public function isModuleEnabled() : bool
    {
        return $this->getFlag(self::XML_PATH_GENERAL_ENABLED);
    }

    /**
     * Check if we should debounce this purge request
     */
    public function shouldDebouncePurgeRequest() : bool
    {
        return $this->isModuleEnabled() && $this->shouldDebouncePurgeRequest;
    }

    /**
     * Set flag to allow skipping the debounce (for flush workflows)
     */
    public function setShouldDebouncePurgeRequest(bool $state) : void
    {
        $this->shouldDebouncePurgeRequest = $state;
    }

    /**
     * Check if staggered/batched purge release is enabled
     */
    public function isStaggerEnabled() : bool
    {
        return $this->getFlag(self::XML_PATH_STAGGER_ENABLED);
    }

    /**
     * Tags per staggered purge chunk
     */
    public function getStaggerBatchSize() : int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_STAGGER_BATCH_SIZE);
    }

    /**
     * Pause, in milliseconds, between staggered purge chunks
     */
    public function getStaggerIntervalMs() : int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_STAGGER_INTERVAL_MS);
    }

    /**
     * Safety budget, in seconds, per staggered release invocation
     */
    public function getStaggerMaxRuntimeSeconds() : int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_STAGGER_MAX_RUNTIME_SECONDS);
    }

    /**
     * Ratio of arrived-during-drain to just-drained tags that counts as lagging
     */
    public function getStaggerLagRatioThreshold() : float
    {
        return (float)$this->scopeConfig->getValue(self::XML_PATH_STAGGER_LAG_RATIO_THRESHOLD);
    }

    /**
     * Consecutive lagging runs before an admin notification is raised
     */
    public function getStaggerLagAlertAfterRuns() : int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_STAGGER_LAG_ALERT_AFTER_RUNS);
    }

    /**
     * Fetch a system config flag
     */
    private function getFlag(string $path, ?string $scope = 'default', ?string $scopeCode = null) : bool
    {
        return (bool)$this->scopeConfig->isSetFlag($path, $scope, $scopeCode);
    }
}
