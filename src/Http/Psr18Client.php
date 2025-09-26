<?php
declare(strict_types=1);

namespace Reve\SDK\Http;

use DateTimeImmutable;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use LogicException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Reve\SDK\Config\ClientConfig;
use Reve\SDK\Contracts\SleeperInterface;
use Reve\SDK\Exceptions\ReveException;

final class Psr18Client implements ClientInterface
{
    private ClientConfig $config;

    private GuzzleClient $client;

    private LoggerInterface $logger;

    private SleeperInterface $sleeper;

    public function __construct(
        ?ClientConfig $config = null,
        ?GuzzleClient $client = null,
        ?LoggerInterface $logger = null,
        ?SleeperInterface $sleeper = null
    ) {
        $this->config = $config ?? new ClientConfig();
        $this->client = $client ?? new GuzzleClient([
            'http_errors' => false,
            'timeout' => $this->config->getTimeout(),
            'connect_timeout' => $this->config->getConnectTimeout(),
        ]);
        $this->logger = $logger ?? new NullLogger();
        $this->sleeper = $sleeper ?? new NativeSleeper();
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
        } catch (GuzzleException $exception) {
            throw new class('HTTP client error: ' . $exception->getMessage(), 0, $exception) extends ReveException implements ClientExceptionInterface {
            };
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
            throw new LogicException('Unexpected retry state without response.');
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
            $date = new DateTimeImmutable($header);
            $diff = $date->format('U.u') - (float) (new DateTimeImmutable())->format('U.u');
            return max(0.1, $diff);
        } catch (\Exception) {
            return null;
        }
    }
}
