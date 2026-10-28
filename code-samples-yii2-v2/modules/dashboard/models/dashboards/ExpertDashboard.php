<?php

declare(strict_types=1);

namespace app\modules\dashboard\models\dashboards;

use app\components\values\Period;
use app\modules\dashboard\helpers\InfoBlockBuilder;
use app\modules\dashboard\models\settings\DashboardSettingPoint;
use app\services\dashboard\ClosingSpeedService;

/**
 * Дашборд эксперта: показатели сотрудника за месяц.
 *
 * Класс отвечает только за представление - собрать блоки, выбрать реакцию,
 * построить ссылку в CRM. Считают показатели сервисы, поэтому здесь нет ни
 * запросов, ни правил премии.
 *
 * Целиком приведён один блок - скорость закрытия заказа: остальные устроены
 * так же и в примере опущены, чтобы файл читался за минуту.
 */
final class ExpertDashboard extends Dashboard
{
    public const string DASHBOARD_TITLE = 'Дашборд эксперта';

    /** Показатель в процентах: от 90 - хорошо, ниже 50 - плохо. */
    private const int SPEED_GOOD_PERCENT = 90;
    private const int SPEED_BAD_PERCENT = 50;

    /** Раздел CRM со списком закрытых заказов - те же данные, из которых считается процент. */
    private const string CRM_CLOSED_ORDERS_URL = '/control-closed';

    public function __construct(
        private readonly int $expertId,
        private readonly Period $period,
        private readonly ClosingSpeedService $closingSpeed,
    ) {
    }

    public function view(): array
    {
        // Остальные блоки дашборда - работа с документами, план на сегодня, таблица
        // услуг, заработок и рабочие часы - собираются так же и в примере опущены.
        return [
            $this->declarationClosingSpeed(),
        ];
    }

    /**
     * Блок "скорость закрытия заказа": процент, реакция и ссылка на заказы в CRM.
     */
    public function declarationClosingSpeed(): array
    {
        $percent = $this->closingSpeed->percentForExpert($this->expertId, $this->period);

        return InfoBlockBuilder::reactionInfoBlock(
            $this->reactionFor($percent),
            'Скорость закрытия заказа',
            $percent === null ? 'Не заполнено' : $percent . '%',
            $this->crmClosedOrdersLink(),
        );
    }

    private function reactionFor(?float $percent): string
    {
        return match (true) {
            $percent === null => InfoBlockBuilder::REACTION_NORMAL,
            $percent >= self::SPEED_GOOD_PERCENT => InfoBlockBuilder::REACTION_GOOD,
            $percent < self::SPEED_BAD_PERCENT => InfoBlockBuilder::REACTION_BAD,
            default => InfoBlockBuilder::REACTION_NORMAL,
        };
    }

    /** Ссылка ведёт на те же заказы, по которым посчитан процент. */
    private function crmClosedOrdersLink(): string
    {
        return self::CRM_CLOSED_ORDERS_URL . '?' . http_build_query([
            'manager' => $this->expertId,
            'year' => $this->period->year(),
            'month' => $this->period->month(),
            'stage' => 'docs_close',
            'service' => implode(',', DashboardSettingPoint::SPEED_RATED_SERVICES),
        ]);
    }
}
