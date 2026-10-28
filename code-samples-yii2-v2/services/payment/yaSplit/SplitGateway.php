<?php

declare(strict_types=1);

namespace app\services\payment\yaSplit;

use app\modules\payment\models\PaymentQueue;

/**
 * Рассрочка со стороны нашего кода.
 *
 * Сервисы зависят от этого интерфейса, а не от HTTP-клиента платёжки: так
 * сценарий оплаты тестируется на заглушке, а смена провайдера рассрочки
 * не выходит за пределы адаптера.
 */
interface SplitGateway
{
    /**
     * Создаёт заказ на стороне платёжки.
     *
     * @throws \app\services\exceptions\ConflictException платёжка отказала или недоступна
     */
    public function createOrder(PaymentQueue $queue, int $paymentId, ?string $returnUrl): SplitCheckout;

    /** Ссылка на оплату уже созданного заказа. */
    public function checkoutUrl(string $transaction): string;
}
