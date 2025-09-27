<?php

declare(strict_types=1);

namespace Reve\SDK\Tests;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Reve\SDK\Config\ClientConfig;
use Reve\SDK\Http\ClientInterface;

/** @internal */
final class FakeHttpClient implements ClientInterface
{
    /** @var array<int, ResponseInterface> */
    private array $responses;

    private ClientConfig $config;

    /**
     * @param array<int, ResponseInterface> $responses
     */
    public function __construct(ClientConfig $config, array $responses)
    {
        $this->config = $config;
        $this->responses = $responses;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (count($this->responses) === 0) {
            return new Response(200, [], json_encode([
                'status' => 'completed',
                'result' => ['prompt' => 'fallback', 'image_urls' => []],
            ], JSON_THROW_ON_ERROR));
        }

        return array_shift($this->responses);
    }

    public function getConfig(): ClientConfig
    {
        return $this->config;
    }
}
