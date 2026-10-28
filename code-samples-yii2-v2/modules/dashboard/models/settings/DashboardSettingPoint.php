<?php

declare(strict_types=1);

namespace app\modules\dashboard\models\settings;

use app\components\main\MainModel;
use app\components\values\Period;
use app\models\Service;
use app\services\dashboard\SpeedStandard;
use yii\db\ActiveQuery;

/**
 * Баллы и нормативы по услуге на конкретный месяц, таблица "dashboard_setting_point".
 *
 * Настройки заводятся на каждый месяц отдельно: пересчитывать прошлые периоды
 * новыми правилами нельзя - у сотрудников по ним уже закрыты премии.
 *
 * @property int $id
 * @property int $service ключ настройки: id услуги или виртуальная группа
 * @property int $month
 * @property int $year
 * @property int|null $point баллы за закрытый заказ
 * @property bool|null $available_for_consultant
 * @property float|null $speed_standard норматив закрытия заказа, часы
 * @property float|null $bonus_fast_completion надбавка за закрытие в срок, %
 * @property float|null $penalty_slow_completion штраф за просрочку, %
 * @property float|null $document_check_speed_standard норматив проверки документов, часы
 * @property int|null $document_check_penalty_point штраф за просрочку проверки, баллы
 */
final class DashboardSettingPoint extends MainModel
{
    /** Пакетная услуга: все её поколения делят один норматив и одни баллы. */
    public const int SETTING_KEY_BUNDLE = -1;

    /**
     * Виртуальные ключи настроек - сводные показатели дашбордов, у которых нет
     * своей услуги в справочнике. Отрицательные, чтобы не пересечься с id услуг.
     *
     * @var list<int>
     */
    public const array VIRTUAL_SETTING_KEYS = [-1, -2, -3, -4, -5, -6, -7, -8, -9, -10, -11, -12];

    /**
     * Поколения пакетной услуги.
     *
     * @var list<int>
     */
    public const array BUNDLE_SERVICES = [
        Service::SERVICE_BUNDLE_1,
        Service::SERVICE_BUNDLE_2,
        Service::SERVICE_BUNDLE_3,
        Service::SERVICE_BUNDLE_4,
        Service::SERVICE_BUNDLE_5,
    ];

    /**
     * Услуги, по которым считается скорость закрытия заказа.
     *
     * @var list<int>
     */
    public const array SPEED_RATED_SERVICES = [
        Service::SERVICE_MAIN,
        Service::SERVICE_REPORT_1_DAY,
        Service::SERVICE_PREMIUM_CONSULT,
        Service::SERVICE_DOC_CHECK,
        Service::SERVICE_REPORT_A,
        Service::SERVICE_REPORT_B,
        Service::SERVICE_ADJUSTMENT,
        Service::SERVICE_FAST_REFUND,
        Service::SERVICE_REPORT_FOR_ADVANCE,
        ...self::BUNDLE_SERVICES,
    ];

    /** Значения плана, которые переносятся на следующий месяц как есть. */
    private const array PLAN_ATTRIBUTES = [
        'point',
        'available_for_consultant',
        'speed_standard',
        'bonus_fast_completion',
        'penalty_slow_completion',
        'document_check_speed_standard',
        'document_check_penalty_point',
    ];

    public static function tableName(): string
    {
        return 'dashboard_setting_point';
    }

    public function rules(): array
    {
        return [
            [['service', 'month', 'year'], 'required'],
            [['service', 'month', 'year', 'point', 'document_check_penalty_point'], 'integer'],
            [['month'], 'integer', 'min' => 1, 'max' => 12],
            [['available_for_consultant'], 'boolean'],
            [
                [
                    'speed_standard',
                    'bonus_fast_completion',
                    'penalty_slow_completion',
                    'document_check_speed_standard',
                ],
                'number',
                'min' => 0,
            ],
            [['service'], 'unique', 'targetAttribute' => ['service', 'month', 'year']],
        ];
    }

    public static function findForPeriod(Period $period): ActiveQuery
    {
        return self::find()->andWhere(['month' => $period->month(), 'year' => $period->year()]);
    }

    /**
     * Все строки настроек, которые должны существовать на месяц.
     *
     * @return list<int>
     */
    public static function settingKeys(): array
    {
        return array_values(array_unique([...self::SPEED_RATED_SERVICES, ...self::VIRTUAL_SETTING_KEYS]));
    }

    /**
     * Нормативы скорости за период, разложенные по услугам.
     *
     * Пакетные услуги настраиваются одной строкой, поэтому её норматив
     * раскладывается по всем поколениям пакета: расчёту достаточно знать услугу
     * заказа и не нужно помнить про виртуальные ключи.
     *
     * @return array<int, SpeedStandard>
     */
    public static function speedStandards(Period $period): array
    {
        $standards = [];

        /** @var self $setting */
        foreach (self::findForPeriod($period)->all(self::getDbSlave()) as $setting) {
            if ($setting->speed_standard === null) {
                continue;
            }

            $standards[(int) $setting->service] = new SpeedStandard(
                (float) $setting->speed_standard,
                (float) ($setting->bonus_fast_completion ?? 0),
                (float) ($setting->penalty_slow_completion ?? 0),
            );
        }

        if (isset($standards[self::SETTING_KEY_BUNDLE])) {
            foreach (self::BUNDLE_SERVICES as $service) {
                $standards[$service] = $standards[self::SETTING_KEY_BUNDLE];
            }
        }

        return $standards;
    }

    /** Переносит значения плана с прошлого месяца: обычно они не меняются. */
    public function copyPlanFrom(?self $source): void
    {
        if ($source === null) {
            return;
        }

        $this->setAttributes($source->getAttributes(self::PLAN_ATTRIBUTES), false);
    }
}
