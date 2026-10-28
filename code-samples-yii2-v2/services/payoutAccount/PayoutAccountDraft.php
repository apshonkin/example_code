<?php

declare(strict_types=1);

namespace app\services\payoutAccount;

/**
 * Реквизиты, которые просят добавить.
 *
 * Сервис принимает готовый набор значений, а не Request и не форму: те же
 * реквизиты приходят из личного кабинета, из CRM и из импорта партнёра, и всем
 * трём нужен один контракт.
 */
final readonly class PayoutAccountDraft
{
    public function __construct(
        public int $userId,
        public string $ownerName,
        public string $accountNumber,
    ) {
    }
}
