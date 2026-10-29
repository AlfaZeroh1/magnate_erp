<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Thrown anywhere to send a clean error to the client. */
class HttpException extends RuntimeException
{
    public array $errors;

    public function __construct(string $message, int $code = 400, array $errors = [])
    {
        parent::__construct($message, $code);
        $this->errors = $errors;
    }

    public static function validation(array $errors, string $message = 'Please correct the highlighted fields.'): self
    {
        return new self($message, 422, $errors);
    }
}
