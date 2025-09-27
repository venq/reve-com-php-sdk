<?php

declare(strict_types=1);

namespace Reve\SDK\Http;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Reve\SDK\Config\ClientConfig;
use Reve\SDK\Exceptions\HttpException;
use Reve\SDK\Exceptions\ServerException;
use Reve\SDK\Exceptions\UnauthorizedException;
use Reve\SDK\Exceptions\TooManyRequestsException;

final class Psr18Client implements ClientInterface
{
    private ClientConfig $config;

    private \GuzzleHttp\Client $client;

    private LoggerInterface $logger;

    private \Reve\SDK\Contracts\SleeperInterface $sleeper;

    public function __construct(
        ?ClientConfig $config = null,
        ?\GuzzleHttp\Client $client = null,
        ?LoggerInterface $logger = null,
        ?\Reve\SDK\Contracts\SleeperInterface $sleeper = null
    ) {
        $this->config = $config ?? new ClientConfig();
        $this->client = $client ?? new \GuzzleHttp\Client([
            'http_errors' => false,
            'timeout' => $this->config->getTimeout(),
            'connect_timeout' => $this->config->getConnectTimeout(),
        ]);
        $this->logger = $logger ?? new \Psr\Log\NullLogger();
        $this->sleeper = $sleeper ?? new \Reve\SDK\Http\NativeSleeper();
    }

    public function getConfig(): ClientConfig
    {
        return $this->config;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        if ($method === 'GET') {
            return $this->sendWithRetry($request);
        }

        return $this->send($request);
    }

    private function send(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->client->send($request, [
                'http_errors' => false,
                'timeout' => $this->config->getTimeout(),
                'connect_timeout' => $this->config->getConnectTimeout(),
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $exception) {
            $message = 'HTTP client error: ' . $exception->getMessage();
            throw new \Reve\SDK\Exceptions\HttpClientException($message, 0, $exception);
        }
    }

    private function sendWithRetry(RequestInterface $request): ResponseInterface
    {
        $attempts = 0;
        $maxAttempts = max(1, $this->config->getMaxGetRetries() + 1);
        /** @var ResponseInterface|null $lastResponse */
        $lastResponse = null;

        while ($attempts < $maxAttempts) {
            ++$attempts;
            $response = $this->send($request);
            $status = $response->getStatusCode();

            if ($status < 400 || !in_array($status, [429, 500, 502, 503, 504], true)) {
                return $response;
            }

            $this->logger->warning('Reve API GET request failed', [
                'status' => $status,
                'attempt' => $attempts,
                'max_attempts' => $maxAttempts,
            ]);

            $lastResponse = $response;

            if ($attempts >= $maxAttempts) {
                break;
            }

            $wait = $this->extractRetryAfterSeconds($response);
            if ($wait === null) {
                $wait = pow(2, $attempts - 1);
            }

            $this->sleeper->sleep($wait);
        }

        if (! $lastResponse instanceof ResponseInterface) {
            throw new \LogicException('Unexpected retry state without response.');
        }

        return $lastResponse;
    }

    private function extractRetryAfterSeconds(ResponseInterface $response): ?float
    {
        if (!$response->hasHeader('Retry-After')) {
            return null;
        }

        $header = $response->getHeaderLine('Retry-After');
        if ($header === '') {
            return null;
        }

        if (is_numeric($header)) {
            return (float) $header;
        }

        try {
            $date = new \DateTimeImmutable($header);
            $diff = $date->format('U.u') - (float) (new \DateTimeImmutable())->format('U.u');
            return max(0.1, $diff);
        } catch (\Exception) {
            return null;
        }
    }
}
