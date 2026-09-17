<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Unit\Model\Config\Backend;

use Magento\Cron\Model\Schedule;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\CronException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;
use SamJUK\CacheDebounce\Model\Config\Backend\CronExpression;

class CronExpressionTest extends TestCase
{
    /**
     * @dataProvider listExpressions
     */
    public function testRejectsWhenLaterListComponentFailsValidation(string $expression)
    {
        $schedule = $this->getMockBuilder(Schedule::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['matchCronExpression'])
            ->getMock();
        $parserError = new CronException(__('Component rejected'));
        $schedule->expects($this->atLeastOnce())
            ->method('matchCronExpression')
            ->willReturnCallback(function ($component, $number) use ($parserError) {
                $this->assertSame(0, $number);
                if ($component === '1') {
                    throw $parserError;
                }
                return true;
            });

        $context = $this->createMock(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createMock(ManagerInterface::class));
        $model = new CronExpression(
            $context,
            $this->createMock(Registry::class),
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(TypeListInterface::class),
            $schedule
        );
        $model->setValue($expression);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Flush Schedule is not a valid cron expression');
        $model->beforeSave();
    }

    public static function listExpressions(): array
    {
        return [
            'literal match' => ['0,1 * * * *'],
            'range match' => ['0-10,1 * * * *'],
            'minute wildcard' => ['*,1 * * * *'],
            'hour wildcard' => ['* *,1 * * *'],
            'day wildcard' => ['* * *,1 * *'],
            'month wildcard' => ['* * * *,1 *'],
            'weekday wildcard' => ['* * * * *,1'],
        ];
    }
}
