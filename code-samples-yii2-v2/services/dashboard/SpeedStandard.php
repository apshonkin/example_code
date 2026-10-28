<?php

declare(strict_types=1);

namespace app\services\dashboard;

/**
 * Норматив скорости закрытия заказа: сколько часов даётся на услугу и что
 * бывает за срок и за просрочку.
 *
 * Правило премии живёт в одном классе: и дашборд эксперта, и отчёт руководителя
 * считают коэффициент одинаково.
 */
final readonly class SpeedStandard
{
    public function __construct(
        public float $hours,
        public float $bonusPercent,
        public float $penaltyPercent,
    ) {
    }

    /** Уложился в норматив - надбавка, не уложился - штраф. */
    public function coefficient(float $spentHours): float
    {
        return $spentHours <= $this->hours
            ? 1 + $this->bonusPercent / 100
            : 1 - $this->penaltyPercent / 100;
    }
}
