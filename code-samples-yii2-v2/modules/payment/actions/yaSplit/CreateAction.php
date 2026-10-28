<?php

declare(strict_types=1);

namespace app\modules\payment\actions\yaSplit;

use app\components\transport\ITransport;
use app\components\transport\Transport;
use app\modules\payment\actions\ApiAction;
use app\services\exceptions\ValidationException;
use app\services\payment\yaSplit\CheckoutService;
use yii\base\Controller;

/**
 * POST /payment/ya-split - ссылка на оплату заказа в рассрочку.
 */
final class CreateAction extends ApiAction
{
    public function __construct(
        string $id,
        Controller $controller,
        private readonly CheckoutService $checkout,
        array $config = [],
    ) {
        parent::__construct($id, $controller, $config);
    }

    public function run(): void
    {
        $this->respond(function (): ITransport {
            $orderId = (int) $this->request->getBodyParam('oid');
            if ($orderId <= 0) {
                throw new ValidationException(['oid' => ['Не передан обязательный параметр oid']]);
            }

            $returnUrl = $this->request->getBodyParam('return_url_success');

            return new Transport(
                $this->checkout->checkoutUrl($orderId, $returnUrl === null ? null : (string) $returnUrl),
                200,
            );
        });
    }
}
