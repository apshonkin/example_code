<?php

declare(strict_types=1);

namespace app\services\payment\yaSplit;

use app\modules\payment\controllers\PaymentFacade;
use app\modules\payment\models\Order;
use app\modules\payment\models\PaymentQueue;
use app\services\exceptions\ConflictException;
use app\services\exceptions\NotFoundException;
use Yii;

/**
 * Оплата заказа в рассрочку: выдача ссылки на оплату.
 *
 * Сценарий целиком здесь, потому что в него ходят и REST-экшен, и повторная
 * отправка ссылки из CRM.
 */
final class CheckoutService
{
    private const LOG_CATEGORY = 'yasplit';

    public function __construct(
        private readonly SplitGateway $gateway,
        private readonly PaymentFacade $payments,
    ) {
    }

    /**
     * Ссылка, по которой клиент оплатит заказ.
     *
     * Повторный запрос по тому же заказу возвращает ссылку на уже созданную
     * оплату: иначе в платёжке копятся брошенные заказы, а клиент видит
     * несколько активных рассрочек на одну услугу.
     */
    public function checkoutUrl(int $orderId, ?string $returnUrl): string
    {
        $order = Order::findOne(['id' => $orderId]);
        if ($order === null) {
            throw new NotFoundException('Заказ не найден');
        }

        $queued = PaymentQueue::createOrUpdate($order);
        if (!$queued->isSuccessStatus()) {
            throw new ConflictException($queued->getBodyAsString());
        }

        // Очередь оплаты только что изменилась - сбрасываем закешированную связь.
        unset($order->paymentQueue);

        $payment = $this->payments->eMerchantYaSplitGetPayment($order->paymentQueue);
        if ($payment->transaction !== null) {
            return $this->gateway->checkoutUrl((string) $payment->transaction);
        }

        $checkout = $this->gateway->createOrder($order->paymentQueue, (int) $payment->id, $returnUrl);

        $payment->transaction = $checkout->transaction;
        if (!$payment->update()) {
            // Заказ в платёжке уже есть, а связать его с оплатой не вышло: без
            // transaction колбэк не найдёт оплату, поэтому наружу это ошибка.
            Yii::error([
                'message' => 'Не удалось сохранить transaction оплаты',
                'paymentId' => (int) $payment->id,
                'transaction' => $checkout->transaction,
                'errors' => $payment->getErrors(),
            ], self::LOG_CATEGORY);

            throw new ConflictException('Не удалось создать оплату заказа');
        }

        return $checkout->url;
    }
}
