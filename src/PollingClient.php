<?php

declare(strict_types=1);

namespace Reve\SDK;

use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Reve\SDK\Contracts\CompletedGeneration;
use Reve\SDK\Contracts\GenerationStatus;
use Reve\SDK\Contracts\SleeperInterface;
use Reve\SDK\Enums\TaskStatus;
use Reve\SDK\Exceptions\PollingException;
use Reve\SDK\Http\NativeSleeper;

final class PollingClient
{
    private ReveClient $client;

    private LoggerInterface $logger;

    private SleeperInterface $sleeper;

    public function __construct(ReveClient $client, ?LoggerInterface $logger = null, ?SleeperInterface $sleeper = null)
    {
        $this->client = $client;
        $this->logger = $logger ?? new NullLogger();
        $this->sleeper = $sleeper ?? new NativeSleeper();
    }

    public function waitUntilCompleted(string $taskId, int $intervalSeconds = 2, int $timeoutSeconds = 300): CompletedGeneration
    {
        $taskId = trim($taskId);
        if ($taskId === '') {
            throw new InvalidArgumentException('Task ID must not be empty.');
        }

        $interval = max(1, $intervalSeconds);
        $deadline = microtime(true) + max(1, $timeoutSeconds);

        while (microtime(true) < $deadline) {
            [$status, $response] = $this->client->getGenerationStatusWithResponse($taskId);

            $this->logger->debug('Polling task status', [
                'task_id' => $taskId,
                'status' => $status->status->value,
            ]);

            if ($status->status === TaskStatus::Failed) {
                throw new PollingException('Task ' . $taskId . ' failed during polling.');
            }

            if ($status->status === TaskStatus::Completed) {
                return $status->ensureCompleted();
            }

            $sleepFor = $this->resolveSleepInterval($status, $response, $interval);
            $this->sleeper->sleep($sleepFor);
        }

        throw new PollingException('Polling timed out for task ' . $taskId . '.');
    }

    private function resolveSleepInterval(GenerationStatus $status, ResponseInterface $response, int $fallback): float
    {
        $retryAfter = $this->extractRetryAfterSeconds($response);
        if ($retryAfter !== null) {
            return $retryAfter;
        }

        if ($status->estimatedWaitSeconds !== null && $status->estimatedWaitSeconds > 0) {
            return (float) min($status->estimatedWaitSeconds, 10);
        }

        return (float) $fallback;
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
            return max(0.1, (float) $header);
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
