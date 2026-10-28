<?php

declare(strict_types=1);

namespace app\modules\v3\actions;

use app\components\transport\ITransport;
use app\models\User;
use app\services\Actor;
use app\services\exceptions\DomainException;
use app\services\exceptions\HttpStatus;
use app\services\exceptions\NotFoundException;
use app\services\exceptions\ValidationException;
use Yii;

/**
 * Транспортная часть REST-экшена.
 *
 * Здесь всё, что относится к HTTP и повторяется в каждом экшене: кто пришёл,
 * что вернуть, каким кодом ответить на доменную ошибку. Наследникам остаётся
 * разобрать запрос и позвать сервис - поэтому экшены получаются в десяток строк
 * и в них нечего дублировать.
 */
abstract class ApiAction extends ModuleAction
{
    /**
     * Выполняет сценарий и отвечает клиенту.
     *
     * @param callable(Actor): ?ITransport $scenario null в ответе - это 204
     */
    protected function respond(callable $scenario): void
    {
        try {
            $this->send($scenario($this->actor()));
        } catch (ValidationException $exception) {
            // Ошибки полей отдаём как есть: фронт подсвечивает конкретные поля формы.
            $this->send($exception->getErrors(), HttpStatus::of($exception));
        } catch (DomainException $exception) {
            $this->send($exception->getMessage(), HttpStatus::of($exception));
        }
    }

    protected function actor(): Actor
    {
        $identity = Yii::$app->getUser()->getIdentity();
        if (!$identity instanceof User) {
            throw new NotFoundException('Пользователь не найден');
        }

        return Actor::fromIdentity($identity);
    }
}
