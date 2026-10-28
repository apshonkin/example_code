<?php

declare(strict_types=1);

namespace app\modules\payment\actions\yaSplit;

use app\components\transport\ITransport;
use app\components\transport\Transport;
use app\modules\payment\actions\ApiAction;
use app\services\exceptions\ValidationException;
use app\services\payment\yaSplit\CallbackHandler;
use yii\base\Controller;

/**
 * POST /payment/ya-split/callback - колбэк платёжки о результате оплаты.
 *
 * Вебхуку важно получить понятный код: 200 - колбэк принят и больше не нужен,
 * 4xx - в теле что-то не то, 5xx - повторить позже. Всё остальное, включая
 * повторные колбэки на одну оплату, разбирает CallbackHandler.
 */
final class CallbackAction extends ApiAction
{
    public function __construct(
        string $id,
        Controller $controller,
        private readonly CallbackHandler $handler,
        array $config = [],
    ) {
        parent::__construct($id, $controller, $config);
    }

    public function run(): void
    {
        $this->respond(function (): ITransport {
            $transaction = (string) $this->request->getBodyParam('order_id');
            $status = (string) $this->request->getBodyParam('status');

            if ($transaction === '' || $status === '') {
                throw new ValidationException([
                    'order_id' => ['Не переданы обязательные параметры order_id и status'],
                ]);
            }

            $this->handler->handle($transaction, $status);

            return new Transport('ok', 200);
        });
    }
}
