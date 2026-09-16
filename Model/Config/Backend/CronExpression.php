<?php

declare(strict_types=1);

namespace SamJUK\CacheDebounce\Model\Config\Backend;

use Magento\Cron\Model\Schedule;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\CronException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Rejects cron expressions Magento's scheduler cannot parse. An invalid
 * expression would otherwise throw during schedule generation and stop
 * every job in the cron group.
 */
class CronExpression extends Value
{
    /** @var Schedule $schedule Used as a parser only, nothing is saved through it */
    private $schedule;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        Schedule $schedule,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->schedule = $schedule;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = trim((string)$this->getValue());

        try {
            $schedule = $this->schedule->setCronExpr($value);
            foreach ($schedule->getCronExprArr() as $part) {
                $schedule->matchCronExpression($part, 0);
            }
        } catch (CronException $e) {
            throw new LocalizedException(__('Flush Schedule is not a valid cron expression: "%1"', $value), $e);
        }

        return parent::beforeSave();
    }
}
