<?php

declare(strict_types=1);

namespace IfCastle\Swoole\Exceptions;

use IfCastle\Async\CancelledExceptionInterface;
use IfCastle\Exceptions\BaseException;

class CancelledException extends BaseException implements CancelledExceptionInterface
{
    public function __construct(string $message = 'The operation was cancelled')
    {
        parent::__construct($message);
    }
}
