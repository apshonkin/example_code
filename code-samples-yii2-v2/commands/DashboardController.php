<?php

declare(strict_types=1);

namespace app\commands;

use app\components\values\Period;
use app\services\dashboard\SettingRolloverService;
use app\services\exceptions\DomainException;
use DateTimeImmutable;
use Yii;
use yii\console\ExitCode;

/**
 * Обслуживание дашбордов.
 */
final class DashboardController extends MainController
{
    private const string LOG_CATEGORY = 'dashboard';

    public function __construct(
        string $id,
        $module,
        private readonly SettingRolloverService $rollover,
        array $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Заводит настройки дашбордов на следующий месяц.
     *
     * Крон, 25-го числа. Месяц можно задать вручную, чтобы перезаполнить период
     * после правок в справочнике услуг: php yii dashboard 2026-10
     *
     * Сценарий целиком в SettingRolloverService: команда разбирает аргумент,
     * печатает результат и переводит доменную ошибку в код возврата - ровно то
     * же, что делает REST-экшен, только транспорт другой.
     */
    public function actionIndex(?string $month = null): int
    {
        $period = $month === null
            ? Period::currentMonth()->next()
            : Period::ofMonth(new DateTimeImmutable($month));

        try {
            $created = $this->rollover->prepare($period);
        } catch (DomainException $exception) {
            Yii::error($exception, self::LOG_CATEGORY);
            $this->stderr("Настройки на {$period->title()} не заведены: {$exception->getMessage()}\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout($created === 0
            ? "Настройки на {$period->title()} уже заведены\n"
            : "Настройки на {$period->title()}: создано строк - {$created}\n");

        return ExitCode::OK;
    }
}
