<?php

declare(strict_types=1);

namespace app\modules\v3\actions\payoutAccount;

use app\components\transport\ITransport;
use app\components\transport\Transport;
use app\modules\v3\actions\ApiAction;
use app\modules\v3\forms\payoutAccount\PayoutAccountForm;
use app\services\Actor;
use app\services\exceptions\ValidationException;
use app\services\payoutAccount\PayoutAccountService;
use yii\base\Controller;

/**
 * POST /v3/user/payout-account - добавить реквизиты для выплат.
 *
 * Экшен разбирает запрос и отдаёт ответ. Права, запись и история - в сервисе.
 */
final class CreateAction extends ApiAction
{
    public function __construct(
        string $id,
        Controller $controller,
        private readonly PayoutAccountService $service,
        array $config = [],
    ) {
        parent::__construct($id, $controller, $config);
    }

    public function run(): void
    {
        $this->respond(function (Actor $actor): ITransport {
            $form = new PayoutAccountForm();
            $form->load($this->request->getBodyParams());

            if (!$form->validate()) {
                throw new ValidationException($form->getErrors());
            }

            return new Transport($this->service->add($form->toDraft($actor), $actor)->toArray(), 201);
        });
    }
}
