<?php
declare(strict_types=1);

namespace Reve\SDK\Exceptions;

use Psr\Http\Message\ResponseInterface;

class BadRequestException extends HttpException
{
    public static function fromDefaultResponse(ResponseInterface $response): self
    {
        $body = (string) $response->getBody();
        return new self('Bad request', $response->getStatusCode(), $body === '' ? null : $body);
    }
}
