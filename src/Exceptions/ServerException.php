<?php
declare(strict_types=1);

namespace Reve\SDK\Exceptions;

use Psr\Http\Message\ResponseInterface;

class ServerException extends HttpException
{
    public static function fromDefaultResponse(ResponseInterface $response): self
    {
        $body = (string) $response->getBody();
        return new self('Server error', $response->getStatusCode(), $body === '' ? null : $body);
    }
}
