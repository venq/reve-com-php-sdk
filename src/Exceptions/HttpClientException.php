<?php

declare(strict_types=1);

namespace Reve\SDK\Exceptions;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

class HttpClientException extends ReveException implements ClientExceptionInterface
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
