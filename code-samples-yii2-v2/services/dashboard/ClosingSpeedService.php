<?php

declare(strict_types=1);

namespace app\services\dashboard;

use app\components\values\Period;
use app\models\MasterVerification;
use app\modules\dashboard\models\settings\DashboardSettingPoint;

/**
 * Показатель "скорость закрытия заказа" за месяц.
 *
 * Сервис отвечает за то, откуда берутся данные, калькулятор - за то, как из них
 * получается процент. Дашборду остаётся показать число.
 */
final class ClosingSpeedService
{
    public function __construct(private readonly ClosingSpeedCalculator $calculator)
    {
    }

    public function percentForExpert(int $expertId, Period $period): ?float
    {
        /** @var list<array{sid: int|string, hours: float|string}> $closedOrders */
        $closedOrders = MasterVerification::find()
            ->closedBy($expertId, $period)
            ->ofServices(DashboardSettingPoint::SPEED_RATED_SERVICES)
            ->exceptCurrentYearDeclarations()
            ->selectClosingDelay()
            ->asArray()
            ->all(MasterVerification::getDbSlave());

        return $this->calculator->percent($closedOrders, DashboardSettingPoint::speedStandards($period));
    }
}
