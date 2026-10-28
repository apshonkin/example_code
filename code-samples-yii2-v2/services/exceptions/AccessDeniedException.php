<?php

declare(strict_types=1);

namespace app\services\exceptions;

/** Объект есть, но у вызывающего нет права на операцию над ним. */
final class AccessDeniedException extends DomainException
{
}
