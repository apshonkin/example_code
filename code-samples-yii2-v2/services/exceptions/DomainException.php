<?php

declare(strict_types=1);

namespace app\services\exceptions;

use RuntimeException;

/**
 * Нарушено бизнес-правило.
 *
 * Домен не знает про HTTP: он называет проблему, а транспорт (REST-экшен,
 * консольная команда, обработчик очереди) решает, как о ней сообщить. Поэтому
 * один и тот же сервис одинаково работает под API и под кроном.
 */
abstract class DomainException extends RuntimeException
{
}
