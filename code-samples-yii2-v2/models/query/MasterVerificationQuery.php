<?php

declare(strict_types=1);

namespace app\models\query;

use app\components\values\Period;
use app\models\MasterVerification;
use yii\db\ActiveQuery;

/**
 * Запросы к проверкам деклараций.
 *
 * Каждое условие расчёта названо своим именем и лежит здесь: показатели
 * дашборда собираются из этих кусков, поэтому запрос читается как условие
 * премии, а не как SQL на полтора экрана.
 *
 * @method MasterVerification[] all($db = null)
 * @method MasterVerification|null one($db = null)
 */
final class MasterVerificationQuery extends ActiveQuery
{
    /** Проверки, закрытые конкретным экспертом внутри периода. */
    public function closedBy(int $expertId, Period $period): self
    {
        return $this
            ->andWhere(['master_verification.verified_by' => $expertId])
            ->andWhere($period->condition('master_verification.closed'));
    }

    /**
     * Только заказы перечисленных услуг.
     *
     * @param list<int> $serviceIds
     */
    public function ofServices(array $serviceIds): self
    {
        return $this
            ->innerJoinWith('master.order', false)
            ->andWhere(['orders.sid' => $serviceIds]);
    }

    /**
     * Декларации не за год оплаты.
     *
     * Норматив скорости написан под текущие декларации: заказ, оплаченный в год
     * своей декларации, в расчёт не идёт. Заказы без даты оплаты сюда тоже
     * не попадают - у них срок закрытия не с чем сравнивать.
     */
    public function exceptCurrentYearDeclarations(): self
    {
        return $this->andWhere('date_part(\'year\', orders.pay_time) <> master.year');
    }

    /**
     * Услуга заказа и часы от первой загрузки документов до закрытия проверки.
     *
     * В БД остаётся только то, что она действительно умеет лучше - разница дат;
     * нормативы, бонусы и штрафы применяет ClosingSpeedCalculator, поэтому
     * правила расчёта покрыты юнит-тестами, а не спрятаны в тексте запроса.
     */
    public function selectClosingDelay(): self
    {
        return $this
            ->innerJoinWith('master.order.documentsStatus', false)
            ->andWhere(['not', ['master_verification.closed' => null]])
            ->andWhere(['not', ['order_documents_status.first_uploaded_date' => null]])
            ->select([
                'sid' => 'orders.sid',
                'hours' => 'extract(epoch from '
                    . '(master_verification.closed - order_documents_status.first_uploaded_date)) / 3600',
            ]);
    }
}
