<?php

declare(strict_types=1);

namespace app\services\payoutAccount;

use app\models\LogAction;
use app\models\LogActionType;
use app\models\UserPayoutAccount;
use app\services\Actor;
use app\services\exceptions\AccessDeniedException;
use app\services\exceptions\ConflictException;
use app\services\exceptions\NotFoundException;
use app\services\exceptions\ValidationException;
use Yii;

/**
 * Реквизиты для выплат: сценарии добавления и удаления.
 *
 * Здесь собрано всё, что должно случиться при изменении реквизитов - проверка
 * права, запись, запись в историю. Экшен только приносит данные и превращает
 * результат в ответ, поэтому те же сценарии вызываются из консольного импорта
 * и покрываются тестами без поднятия HTTP-запроса.
 */
final class PayoutAccountService
{
    public function add(PayoutAccountDraft $draft, Actor $actor): UserPayoutAccount
    {
        if (!$actor->canManage($draft->userId)) {
            throw new AccessDeniedException('Недостаточно прав для управления реквизитами другого пользователя');
        }

        $account = new UserPayoutAccount([
            'uid' => $draft->userId,
            'owner_name' => $draft->ownerName,
            'account_number' => $draft->accountNumber,
            'created_by' => $actor->id,
        ]);

        if (!$account->save()) {
            throw new ValidationException($account->getErrors());
        }

        $this->writeHistory($account, $actor, 'добавил');

        return $account;
    }

    public function remove(int $id, Actor $actor): void
    {
        $account = UserPayoutAccount::findOne($id);
        if ($account === null) {
            throw new NotFoundException('Реквизиты не найдены');
        }

        if (!$actor->canManage((int) $account->uid)) {
            throw new AccessDeniedException('Недостаточно прав для удаления реквизитов другого пользователя');
        }

        if ($account->delete() === false) {
            throw new ConflictException('Не удалось удалить реквизиты');
        }

        // Модель после delete() ещё держит значения полей - в историю пишем уже её.
        $this->writeHistory($account, $actor, 'удалил');
    }

    /**
     * История изменений реквизитов.
     *
     * Журнал ведём после самой операции и не роняем её, если запись не удалась:
     * недоступный журнал не повод отказать клиенту в удалении своих реквизитов.
     * Номер счёта в историю уходит замаскированным - по логам его не собрать.
     */
    private function writeHistory(UserPayoutAccount $account, Actor $actor, string $action): void
    {
        $log = new LogAction();
        $log->setBaseParams((int) $account->uid, creator: $actor->id);
        $log->type = LogActionType::USER_PAYOUT_ACCOUNT;
        $log->description = sprintf(
            'Пользователь [%d] %s реквизиты %s (%s)',
            $actor->id,
            $action,
            $account->getMaskedAccountNumber(),
            (string) $account->owner_name,
        );

        if (!$log->save()) {
            Yii::error([
                'message' => 'Не удалось записать историю по реквизитам выплат',
                'accountId' => (int) $account->id,
                'errors' => $log->getErrors(),
            ], 'payoutAccount');
        }
    }
}
