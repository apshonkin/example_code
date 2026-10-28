<?php

declare(strict_types=1);

namespace app\services\exceptions;

/** Входные данные не прошли проверку: несёт ошибки по атрибутам, как их отдаёт Model::getErrors(). */
final class ValidationException extends DomainException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(private readonly array $errors, string $message = 'Данные не прошли проверку')
    {
        parent::__construct($message);
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
