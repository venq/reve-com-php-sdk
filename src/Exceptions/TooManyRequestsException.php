<?php

declare(strict_types=1);

namespace Reve\SDK\Exceptions;

use Psr\Http\Message\ResponseInterface;

class TooManyRequestsException extends HttpException
{
    public function __construct(ResponseInterface $response)
    {
        parent::__construct('Too many requests', $response->getStatusCode(), (string) $response->getBody());
    }
}
