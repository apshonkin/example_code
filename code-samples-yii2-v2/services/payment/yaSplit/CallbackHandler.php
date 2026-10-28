<?php

declare(strict_types=1);

namespace app\services\payment\yaSplit;

use app\modules\order\actions\orderTrait\Pay;
use app\modules\order\models\Order;
use app\modules\payment\models\Payment;
use app\modules\payment\models\PaymentQueue;
use app\modules\payment\models\sberpromo\SberPromoUse;
use app\services\exceptions\ConflictException;
use app\services\exceptions\NotFoundException;
use Throwable;
use Yii;
use yii\db\Transaction;

/**
 * Колбэк рассрочки: платёжка сообщает, что клиент оплатил заказ.
 *
 * Сценарий рассчитан на то, что колбэк придёт больше одного раза и что два
 * колбэка придут одновременно - платёжки так делают при таймаутах. Поэтому
 * оплата проводится ровно один раз, а побочные эффекты - после коммита.
 */
final class CallbackHandler
{
    use Pay;

    /** Единственный статус платёжки, по которому оплата считается прошедшей. */
    private const STATUS_APPROVED = 'approved';

    private const LOG_CATEGORY = 'yasplit';

    public function handle(string $transaction, string $status): void
    {
        // Читаем с мастера: оплата могла быть создана секунду назад, слейв ещё не догнал.
        $payment = Payment::findOne(['transaction' => $transaction]);
        if ($payment === null) {
            throw new NotFoundException('Оплата по этому заказу не найдена');
        }

        if ($status !== self::STATUS_APPROVED) {
            // Отказ и промежуточные статусы просто подтверждаем, чтобы платёжка не повторяла колбэк.
            return;
        }

        // Перевод в "проведена" - атомарный: если строку уже забрал параллельный
        // колбэк, обновится 0 строк и второй раз мы заказ не оплатим.
        $completed = Payment::updateAll(
            ['status' => Payment::STATUS_COMPLETE],
            ['and', ['id' => (int) $payment->id], ['not', ['status' => Payment::STATUS_COMPLETE]]],
        );

        if ($completed === 0) {
            return;
        }

        $payment->refresh();
        $this->payOrders($payment);
    }

    /**
     * Проводит оплату по заказам клиента.
     *
     * Сначала пробуем заказ, для которого оплата и создавалась, и только если не
     * получилось - любой другой неоплаченный заказ клиента: деньги пришли, они
     * не должны зависнуть на балансе.
     *
     * Уровень SERIALIZABLE нужен потому, что параллельные колбэки платёжки
     * приходят на один и тот же заказ.
     */
    private function payOrders(Payment $payment): void
    {
        $dbTransaction = Yii::$app->getDb()->beginTransaction(Transaction::SERIALIZABLE);

        try {
            $paid = false;
            if ($payment->paymentQueue !== null) {
                $this->applyCardPromo($payment);
                $paid = $this->payOrder((int) $payment->paymentQueue->order_id);
            }

            if (!$paid) {
                $this->payAnyUnpaidOrder((int) $payment->uid);
            }

            $dbTransaction->commit();
        } catch (Throwable $exception) {
            $dbTransaction->rollBack();

            Yii::error($exception, self::LOG_CATEGORY);

            // Наружу отдаём ошибку: платёжка повторит колбэк, и оплата дойдёт.
            throw new ConflictException('Не удалось провести оплату заказа');
        }

        // Заметки и уведомления шлём только после коммита: запись в user_note
        // внутри SERIALIZABLE-транзакции роняет её с 40001 на дублирующихся колбэках.
        $this->executeAfterCommitTasks();
    }

    private function payOrder(int $orderId): bool
    {
        $order = Order::findOne($orderId);
        if ($order === null) {
            return false;
        }

        return $this->pay($order, true, true)->isSuccessStatus();
    }

    private function payAnyUnpaidOrder(int $userId): bool
    {
        $orders = Order::find()
            ->andWhere(['uid' => $userId, 'status' => Order::STATUS_NEW])
            ->orderBy(['updated' => SORT_DESC])
            ->all();

        $paid = false;
        foreach ($orders as $order) {
            $paid = $this->pay($order, true, true)->isSuccessStatus() || $paid;
        }

        return $paid;
    }

    /**
     * Применяет акцию по банковской карте, если оплата подошла под условия.
     *
     * Акция - приятный бонус, а не часть оплаты: её ошибка не должна отменять
     * уже прошедший платёж, поэтому исключение только пишем в лог.
     */
    private function applyCardPromo(Payment $payment): void
    {
        if ((float) $payment->sum !== (float) PaymentQueue::getVerificationCost()) {
            return;
        }

        try {
            $promo = SberPromoUse::checkCardInfo($payment);
            if ($promo === null) {
                $payment->paymentQueue->promo_type = null;
                $payment->paymentQueue->save();

                return;
            }

            (new SberPromoUse())->apply($promo->id, (int) $payment->paymentQueue->order_id, (int) $payment->id);
        } catch (Throwable $exception) {
            Yii::error([
                'message' => 'Не удалось применить акцию по карте',
                'paymentId' => (int) $payment->id,
                'error' => $exception->getMessage(),
            ], self::LOG_CATEGORY);
        }
    }
}
