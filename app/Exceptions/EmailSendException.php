<?php

namespace App\Exceptions;

use App\Models\EmailLog;
use RuntimeException;
use Throwable;

class EmailSendException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?EmailLog $emailLog = null,
        public readonly int $statusCode = 422,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
