<?php

declare(strict_types=1);

namespace Reve\SDK\Exceptions;

use Psr\Http\Message\ResponseInterface;

class HttpException extends ReveException
{
    private int $statusCode;

    private ?string $responseBody;

    public function __construct(string $message, int $statusCode, ?string $responseBody = null)
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    public static function fromResponse(string $message, ResponseInterface $response): self
    {
        $body = (string) $response->getBody();
        return new self($message, $response->getStatusCode(), $body === '' ? null : $body);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
