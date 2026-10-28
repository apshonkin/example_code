<?php

declare(strict_types=1);

namespace app\services\exceptions;

/**
 * Единственное место, где доменные ошибки встречаются с HTTP.
 *
 * Пока таблица живёт здесь, статус ответа не размазан по экшенам: новый вид
 * доменной ошибки достаточно добавить в match, и его одинаково увидят все
 * версии API.
 */
final class HttpStatus
{
    public static function of(DomainException $exception): int
    {
        return match (true) {
            $exception instanceof NotFoundException => 404,
            $exception instanceof AccessDeniedException => 403,
            $exception instanceof ConflictException => 409,
            $exception instanceof ValidationException => 400,
            default => 500,
        };
    }
}
