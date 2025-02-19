<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    private const XML_PATH_GENERAL_ENABLED = 'samjuk_cache_debounce/general/enabled';

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
     * Fetch a system config flag
     */
    private function getFlag(string $path, ?string $scope = 'default', ?string $scopeCode = null) : bool
    {
        return (bool)$this->scopeConfig->isSetFlag($path, $scope, $scopeCode);
    }

    /**
     * Fetch a system config value
     */
    private function getValue(string $path, ?string $scope = 'default', ?string $scopeCode = null)
    {
        return $this->scopeConfig->getValue($path, $scope, $scopeCode);
    }
}
