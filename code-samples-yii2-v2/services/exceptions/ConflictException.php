<?php

declare(strict_types=1);

namespace app\services\exceptions;

/** Операция невозможна в текущем состоянии объекта или внешней системы. */
final class ConflictException extends DomainException
{
}
