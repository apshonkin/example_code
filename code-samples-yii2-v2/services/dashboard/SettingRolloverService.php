<?php

declare(strict_types=1);

namespace app\services\dashboard;

use app\components\values\Period;
use app\modules\dashboard\models\settings\DashboardSettingPoint;
use app\services\exceptions\ValidationException;
use Yii;

/**
 * Подготовка настроек дашбордов на следующий месяц.
 *
 * Руководитель правит планы вручную, но строки должны существовать до начала
 * месяца - иначе первого числа дашборды пустые. Крон создаёт их заранее и
 * переносит значения с текущего месяца.
 */
final class SettingRolloverService
{
    /**
     * Заводит строки настроек на период.
     *
     * Идемпотентно: если настройки на период уже есть, повторный запуск ничего
     * не меняет - крон можно перезапускать руками и не бояться дублей.
     *
     * @return int сколько строк создано
     */
    public function prepare(Period $period): int
    {
        if (DashboardSettingPoint::findForPeriod($period)->exists()) {
            return 0;
        }

        /** @var array<int, DashboardSettingPoint> $previous */
        $previous = DashboardSettingPoint::findForPeriod($period->previous())
            ->indexBy('service')
            ->all(DashboardSettingPoint::getDbSlave());

        return (int) Yii::$app->getDb()->transaction(
            function () use ($period, $previous): int {
                $created = 0;

                foreach (DashboardSettingPoint::settingKeys() as $settingKey) {
                    $setting = new DashboardSettingPoint([
                        'service' => $settingKey,
                        'month' => $period->month(),
                        'year' => $period->year(),
                    ]);
                    $setting->copyPlanFrom($previous[$settingKey] ?? null);

                    if (!$setting->save()) {
                        // Настройки заводятся пачкой: одна невалидная строка - повод
                        // разобраться, а не оставить месяц заполненным наполовину.
                        throw new ValidationException($setting->getErrors());
                    }

                    $created++;
                }

                return $created;
            },
        );
    }
}
