<?php

declare(strict_types=1);

namespace app\services\dashboard;

/**
 * Скорость закрытия заказов эксперта в процентах.
 *
 * По каждому закрытому заказу смотрим, уложился ли эксперт в норматив своей
 * услуги, и усредняем коэффициенты. 100% - работа точно по нормативу, больше -
 * заказы закрываются быстрее срока.
 *
 * Класс ничего не знает про БД и Yii: на входе строки и нормативы, на выходе
 * число. Поэтому правила расчёта проверяются юнит-тестом за миллисекунды.
 */
final class ClosingSpeedCalculator
{
    /**
     * @param list<array{sid: int|string, hours: float|string}> $closedOrders
     * @param array<int, SpeedStandard> $standards норматив по id услуги
     *
     * @return float|null null - у эксперта нет закрытых заказов за период
     */
    public function percent(array $closedOrders, array $standards): ?float
    {
        $coefficients = [];

        foreach ($closedOrders as $order) {
            $hours = (float) $order['hours'];
            if ($hours < 0) {
                // Закрытие раньше загрузки документов - данные битые, в среднее не берём.
                continue;
            }

            // Услуга без норматива не должна ни поднимать, ни опускать показатель.
            $coefficients[] = ($standards[(int) $order['sid']] ?? null)?->coefficient($hours) ?? 1.0;
        }

        if ($coefficients === []) {
            return null;
        }

        return round(array_sum($coefficients) / count($coefficients) * 100, 2);
    }
}
