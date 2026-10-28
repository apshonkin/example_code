<?php

declare(strict_types=1);

namespace app\models\query;

use app\models\UserPayoutAccount;
use yii\db\ActiveQuery;

/**
 * Запросы к реквизитам выплат.
 *
 * Условия выборки живут здесь, а не в вызывающем коде: "реквизиты клиента" и
 * "актуальные реквизиты" описаны один раз, читаются как текст и переиспользуются
 * из API, CRM и консоли.
 *
 * @method UserPayoutAccount[] all($db = null)
 * @method UserPayoutAccount|null one($db = null)
 */
final class UserPayoutAccountQuery extends ActiveQuery
{
    public function forUser(int $userId): self
    {
        return $this->andWhere(['uid' => $userId]);
    }

    public function byNumber(string $accountNumber): self
    {
        return $this->andWhere(['account_number' => $accountNumber]);
    }

    /** Сначала свежие: список в личном кабинете показывает последние добавленные. */
    public function newestFirst(): self
    {
        return $this->orderBy(['created' => SORT_DESC, 'id' => SORT_DESC]);
    }
}
