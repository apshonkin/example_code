<?php

declare(strict_types=1);

namespace app\services\payment\yaSplit;

use app\components\yandex\YaSplit;
use app\modules\payment\models\PaymentQueue;
use app\services\exceptions\ConflictException;
use Yii;

/**
 * Адаптер поверх компонента YaSplit.
 *
 * Компонент отдаёт результат через свои поля requestResult/requestError -
 * работать с этим состоянием в сценарии оплаты неудобно и легко забыть проверку.
 * Адаптер оставляет наружу только результат и исключение, а разбор ответа
 * платёжки держит в одном месте.
 */
final class YaSplitGateway implements SplitGateway
{
    private const LOG_CATEGORY = 'yasplit';

    public function __construct(private readonly YaSplit $api)
    {
    }

    public function createOrder(PaymentQueue $queue, int $paymentId, ?string $returnUrl): SplitCheckout
    {
        $this->api->create($queue, $paymentId, $returnUrl);

        if (!empty($this->api->requestError)) {
            Yii::error([
                'message' => 'Я.Сплит отказал в создании заказа',
                'paymentId' => $paymentId,
                'error' => $this->api->requestError,
            ], self::LOG_CATEGORY);

            throw new ConflictException('Не удалось создать оплату в Я.Сплит');
        }

        return new SplitCheckout(
            (string) $this->api->requestResult['order_id'],
            (string) $this->api->requestResult['checkout_url'],
        );
    }

    public function checkoutUrl(string $transaction): string
    {
        return YaSplit::getCheckoutUrl($transaction);
    }
}
