<?php

declare(strict_types=1);

namespace app\tests\unit\services\dashboard;

use app\services\dashboard\ClosingSpeedCalculator;
use app\services\dashboard\SpeedStandard;
use Codeception\Test\Unit;

/**
 * Правила расчёта скорости закрытия заказа.
 *
 * Тест без БД и без приложения - именно за этим расчёт вынесен из запроса и из
 * дашборда: премию сотрудников считает код, который можно проверить целиком.
 */
final class ClosingSpeedCalculatorTest extends Unit
{
    /** Услуга: норматив 48 часов, +10% за срок, -20% за просрочку. */
    private const SERVICE_ID = 38;

    private ClosingSpeedCalculator $calculator;

    protected function _before(): void
    {
        $this->calculator = new ClosingSpeedCalculator();
    }

    public function testNoClosedOrdersMeansNothingToShow(): void
    {
        $this->assertNull($this->calculator->percent([], $this->standards()));
    }

    public function testOrderClosedInTimeGetsBonus(): void
    {
        $percent = $this->calculator->percent(
            [['sid' => self::SERVICE_ID, 'hours' => 40.0]],
            $this->standards(),
        );

        $this->assertSame(110.0, $percent);
    }

    public function testOrderClosedExactlyOnStandardIsStillInTime(): void
    {
        $percent = $this->calculator->percent(
            [['sid' => self::SERVICE_ID, 'hours' => 48.0]],
            $this->standards(),
        );

        $this->assertSame(110.0, $percent);
    }

    public function testOverdueOrderGetsPenalty(): void
    {
        $percent = $this->calculator->percent(
            [['sid' => self::SERVICE_ID, 'hours' => 72.0]],
            $this->standards(),
        );

        $this->assertSame(80.0, $percent);
    }

    public function testServiceWithoutStandardDoesNotAffectResult(): void
    {
        $percent = $this->calculator->percent(
            [['sid' => 999, 'hours' => 500.0]],
            $this->standards(),
        );

        $this->assertSame(100.0, $percent);
    }

    public function testBrokenDatesAreSkipped(): void
    {
        $percent = $this->calculator->percent(
            [
                ['sid' => self::SERVICE_ID, 'hours' => -5.0],
                ['sid' => self::SERVICE_ID, 'hours' => 40.0],
            ],
            $this->standards(),
        );

        $this->assertSame(110.0, $percent, 'Заказ с закрытием раньше загрузки документов в среднее не идёт');
    }

    public function testResultIsAverageOfCoefficients(): void
    {
        $percent = $this->calculator->percent(
            [
                ['sid' => self::SERVICE_ID, 'hours' => 40.0],
                ['sid' => self::SERVICE_ID, 'hours' => 72.0],
            ],
            $this->standards(),
        );

        $this->assertSame(95.0, $percent);
    }

    /** Строки из БД приходят строками - расчёт обязан это выдерживать. */
    public function testStringValuesFromDatabase(): void
    {
        $percent = $this->calculator->percent(
            [['sid' => (string) self::SERVICE_ID, 'hours' => '40.5']],
            $this->standards(),
        );

        $this->assertSame(110.0, $percent);
    }

    /**
     * @return array<int, SpeedStandard>
     */
    private function standards(): array
    {
        return [self::SERVICE_ID => new SpeedStandard(48.0, 10.0, 20.0)];
    }
}
