<?php

declare(strict_types=1);

namespace app\modules\v3\actions\payoutAccount;

use app\components\transport\ITransport;
use app\modules\v3\actions\ApiAction;
use app\services\Actor;
use app\services\payoutAccount\PayoutAccountService;
use yii\base\Controller;

/**
 * DELETE /v3/user/payout-account/<id> - удалить реквизиты.
 */
final class DeleteAction extends ApiAction
{
    public function __construct(
        string $id,
        Controller $controller,
        private readonly PayoutAccountService $service,
        array $config = [],
    ) {
        parent::__construct($id, $controller, $config);
    }

    public function run(int $id): void
    {
        $this->respond(function (Actor $actor) use ($id): ?ITransport {
            $this->service->remove($id, $actor);

            return null;
        });
    }
}
