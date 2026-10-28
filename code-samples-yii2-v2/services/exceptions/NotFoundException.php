<?php

declare(strict_types=1);

namespace app\services\exceptions;

/** Объекта, над которым просят выполнить операцию, не существует. */
final class NotFoundException extends DomainException
{
}
