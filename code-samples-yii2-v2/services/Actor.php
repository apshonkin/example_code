<?php

declare(strict_types=1);

namespace app\services;

use app\models\User;

/**
 * Кто выполняет операцию.
 *
 * Сервисы принимают Actor, а не читают Yii::$app->user: тогда их видно из
 * консоли и очередей, а права проверяются в одном месте, а не в каждом экшене.
 */
final readonly class Actor
{
    public function __construct(public int $id, private bool $isStaff)
    {
    }

    public static function fromIdentity(User $identity): self
    {
        return new self((int) $identity->getId(), $identity->rightTo(User::RIGHT_BACKEND));
    }

    /** Технический вызывающий: крон и импорт работают от имени системы. */
    public static function system(): self
    {
        return new self(0, true);
    }

    /** Свои данные клиент правит сам, чужие - только сотрудник. */
    public function canManage(int $ownerId): bool
    {
        return $this->id === $ownerId || $this->isStaff;
    }
}
