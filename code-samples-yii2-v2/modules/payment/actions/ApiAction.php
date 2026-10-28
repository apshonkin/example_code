<?php

declare(strict_types=1);

namespace app\modules\payment\actions;

use app\components\transport\ITransport;
use app\services\exceptions\DomainException;
use app\services\exceptions\HttpStatus;
use app\services\exceptions\ValidationException;

/**
 * Транспортная часть экшенов модуля оплат.
 *
 * У каждого модуля проекта свой ModuleAction (свои Response и ErrorHandler),
 * поэтому базовый класс тоже свой - а правило перевода доменной ошибки в код
 * ответа общее и лежит в HttpStatus.
 */
abstract class ApiAction extends ModuleAction
{
    /**
     * @param callable(): ?ITransport $scenario
     */
    protected function respond(callable $scenario): void
    {
        try {
            $this->send($scenario());
        } catch (ValidationException $exception) {
            $this->send($exception->getErrors(), HttpStatus::of($exception));
        } catch (DomainException $exception) {
            $this->send($exception->getMessage(), HttpStatus::of($exception));
        }
    }
}
