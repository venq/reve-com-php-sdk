<?php

declare(strict_types=1);

namespace Reve\SDK\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Reve\SDK\Config\ClientConfig;
use Reve\SDK\Exceptions\PollingException;
use Reve\SDK\Http\ClientInterface;
use Reve\SDK\PollingClient;
use Reve\SDK\ReveClient;

final class PollingClientTest extends TestCase
{
    public function testWaitUntilCompletedHonoursRetryAfter(): void
    {
        $responses = [
            new Response(200, ['Retry-After' => '0.5'], json_encode([
                'status' => 'queued',
                'estimated_wait_seconds' => 5,
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'status' => 'running',
                'estimated_wait_seconds' => 3,
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'status' => 'completed',
                'result' => [
                    'prompt' => 'final',
                    'image_urls' => ['https://cdn.test/result.png'],
                    'completed_at' => '2025-09-25T20:00:00Z',
                ],
            ], JSON_THROW_ON_ERROR)),
        ];

        $config = ClientConfig::createOfficial('Bearer token');
        $httpClient = new FakeHttpClient($config, $responses);
        $reveClient = new ReveClient($config, $httpClient, new NullLogger());
        $sleeper = new RecordingSleeper();
        $poller = new PollingClient($reveClient, new NullLogger(), $sleeper);

        $result = $poller->waitUntilCompleted('task-123', intervalSeconds: 2, timeoutSeconds: 10);

        self::assertSame(['https://cdn.test/result.png'], $result->imageUrls);
        self::assertNotEmpty($sleeper->calls);
        self::assertEqualsWithDelta(0.5, $sleeper->calls[0], 0.0001);
    }

    public function testThrowsWhenTaskFails(): void
    {
        $responses = [
            new Response(200, [], json_encode([
                'status' => 'failed',
                'warnings' => ['something went wrong'],
            ], JSON_THROW_ON_ERROR)),
        ];

        $config = ClientConfig::createOfficial();
        $httpClient = new FakeHttpClient($config, $responses);
        $reveClient = new ReveClient($config, $httpClient, new NullLogger());
        $poller = new PollingClient($reveClient, new NullLogger(), new RecordingSleeper());

        $this->expectException(PollingException::class);
        $poller->waitUntilCompleted('task-err');
    }
}
