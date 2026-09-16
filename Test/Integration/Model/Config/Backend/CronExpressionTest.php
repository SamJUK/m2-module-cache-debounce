<?php declare(strict_types=1);

namespace SamJUK\CacheDebounce\Test\Integration\Model\Config\Backend;

use PHPUnit\Framework\TestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\ObjectManager;
use SamJUK\CacheDebounce\Model\Config\Backend\CronExpression;

class CronExpressionTest extends TestCase
{
    private const PATH = 'samjuk_cache_debounce/cron/flush_schedule';

    private function backendModel(string $value): CronExpression
    {
        return ObjectManager::getInstance()->create(CronExpression::class)
            ->setPath(self::PATH)
            ->setValue($value);
    }

    /**
     * @dataProvider validExpressions
     */
    public function testAcceptsValidExpressions(string $expression)
    {
        $model = $this->backendModel($expression);
        $model->beforeSave();

        $this->assertSame($expression, $model->getValue());
    }

    public static function validExpressions(): array
    {
        return [
            ['*/15 * * * *'],
            ['0 2 * * *'],
            ['30 1-5,10-20/5 * jan-jun mon-fri'],
            ['0 0 1 * * 2030'],
        ];
    }

    /**
     * @dataProvider invalidExpressions
     */
    public function testRejectsInvalidExpressions(string $expression)
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not a valid cron expression');

        $this->backendModel($expression)->beforeSave();
    }

    public static function invalidExpressions(): array
    {
        return [
            'empty' => [''],
            'too few parts' => ['*/15 * *'],
            'too many parts' => ['* * * * * * *'],
            'garbage part' => ['abc * * * *'],
            'bad modulus' => ['*/x * * * *'],
            'bad range' => ['1-2-3 * * * *'],
        ];
    }
}
