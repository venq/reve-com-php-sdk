<?php
declare(strict_types=1);

namespace Reve\SDK;

use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Reve\SDK\Config\ClientConfig;
use Reve\SDK\Contracts\CreateImageRequest;
use Reve\SDK\Contracts\CreateImageResponse;
use Reve\SDK\Contracts\EditImageRequest;
use Reve\SDK\Contracts\EditImageResponse;
use Reve\SDK\Contracts\GenerationStatus;
use Reve\SDK\Contracts\RemixRequest;
use Reve\SDK\Contracts\RemixResponse;
use Reve\SDK\Exceptions\BadRequestException;
use Reve\SDK\Exceptions\HttpException;
use Reve\SDK\Exceptions\SerializationException;
use Reve\SDK\Exceptions\ServerException;
use Reve\SDK\Exceptions\TooManyRequestsException;
use Reve\SDK\Exceptions\UnauthorizedException;
use Reve\SDK\Http\ClientInterface;
use Reve\SDK\Http\Psr18Client;

final class ReveClient
{
    private ClientConfig $config;

    private ClientInterface $httpClient;

    private LoggerInterface $logger;

    private ?PollingClient $pollingClient = null;

    public function __construct(?ClientConfig $config = null, ?ClientInterface $httpClient = null, ?LoggerInterface $logger = null)
    {
        $this->config = $config ?? ClientConfig::fromEnv();
        $this->logger = $logger ?? new NullLogger();
        $this->httpClient = $httpClient ?? new Psr18Client($this->config, null, $this->logger);
    }

    public static function createDefault(): self
    {
        return new self(ClientConfig::fromEnv());
    }

    public function getConfig(): ClientConfig
    {
        return $this->config;
    }

    public function getPolling(): PollingClient
    {
        if ($this->pollingClient === null) {
            $this->pollingClient = new PollingClient($this, $this->logger);
        }

        return $this->pollingClient;
    }

    public function createImage(CreateImageRequest $request): CreateImageResponse
    {
        $uri = $this->buildCreateImageUri();
        $payload = $request->toPayload();
        $response = $this->postJson($uri, $payload);
        $data = $this->decodeJson($response, 'createImage');
        return CreateImageResponse::fromArray($data);
    }

    public function getGenerationStatus(string $taskId): GenerationStatus
    {
        [$status] = $this->requestStatus($taskId);
        return $status;
    }

    /**
     * @return array{GenerationStatus, ResponseInterface}
     */
    public function getGenerationStatusWithResponse(string $taskId): array
    {
        return $this->requestStatus($taskId);
    }

    public function editImage(EditImageRequest $request): EditImageResponse
    {
        $uri = $this->buildEditUri();

        if ($request->usesMultipart()) {
            $parts = $request->toMultipartPayload();
            $payload = new MultipartStream($parts);
            $headers = $this->defaultHeaders();
            $headers['Content-Type'] = 'multipart/form-data; boundary=' . $payload->getBoundary();
            $httpRequest = new Request('POST', $uri, $headers, $payload);
        } else {
            $body = $this->encodeJson($request->toJsonPayload());
            $headers = $this->defaultHeaders();
            $headers['Content-Type'] = 'application/json';
            $httpRequest = new Request('POST', $uri, $headers, $body);
        }

        $response = $this->httpClient->sendRequest($httpRequest);
        $this->throwIfError($response);
        $data = $this->decodeJson($response, 'editImage');
        return new EditImageResponse((string) ($data['task_id'] ?? ''), isset($data['warnings']) ? (array) $data['warnings'] : null);
    }

    public function remix(RemixRequest $request): RemixResponse
    {
        $uri = $this->buildRemixUri();

        if ($request->usesMultipart()) {
            $parts = $request->toMultipartPayload();
            $payload = new MultipartStream($parts);
            $headers = $this->defaultHeaders();
            $headers['Content-Type'] = 'multipart/form-data; boundary=' . $payload->getBoundary();
            $httpRequest = new Request('POST', $uri, $headers, $payload);
        } else {
            $body = $this->encodeJson($request->toJsonPayload());
            $headers = $this->defaultHeaders();
            $headers['Content-Type'] = 'application/json';
            $httpRequest = new Request('POST', $uri, $headers, $body);
        }

        $response = $this->httpClient->sendRequest($httpRequest);
        $this->throwIfError($response);
        $data = $this->decodeJson($response, 'remix');
        return new RemixResponse((string) ($data['task_id'] ?? ''), isset($data['warnings']) ? (array) $data['warnings'] : null);
    }

    /**
     * @return array{0: GenerationStatus, 1: ResponseInterface}
     */
    private function requestStatus(string $taskId): array
    {
        $taskId = trim($taskId);
        if ($taskId === '') {
            throw new InvalidArgumentException('Task ID must not be empty.');
        }

        $uri = $this->buildStatusUri($taskId);
        $request = new Request('GET', $uri, $this->defaultHeaders());
        $response = $this->httpClient->sendRequest($request);
        $this->throwIfError($response);
        $data = $this->decodeJson($response, 'getGenerationStatus');
        $status = GenerationStatus::fromArray($data);
        return [$status, $response];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postJson(string $uri, array $payload): ResponseInterface
    {
        $body = $this->encodeJson($payload);
        $headers = $this->defaultHeaders();
        $headers['Content-Type'] = 'application/json';
        $request = new Request('POST', $uri, $headers, $body);
        $response = $this->httpClient->sendRequest($request);
        $this->throwIfError($response);
        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function defaultHeaders(): array
    {
        return $this->config->getDefaultHeaders();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeJson(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $exception) {
            throw new SerializationException('Failed to encode payload to JSON: ' . $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(ResponseInterface $response, string $context): array
    {
        $body = (string) $response->getBody();
        if ($body === '') {
            throw new SerializationException('Empty response body for ' . $context);
        }

        try {
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new SerializationException('Failed to decode ' . $context . ' response: ' . $exception->getMessage());
        }

        if (!is_array($decoded)) {
            throw new SerializationException('Invalid JSON structure for ' . $context);
        }

        return $decoded;
    }

    private function throwIfError(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        if ($status < 400) {
            return;
        }

        if ($status === 400) {
            throw BadRequestException::fromDefaultResponse($response);
        }

        if ($status === 401 || $status === 403) {
            throw UnauthorizedException::fromDefaultResponse($response);
        }

        if ($status === 429) {
            throw new TooManyRequestsException($response);
        }

        if ($status >= 500) {
            throw ServerException::fromDefaultResponse($response);
        }

        throw HttpException::fromResponse('Unexpected HTTP status ' . $status, $response);
    }

    private function buildCreateImageUri(): string
    {
        if ($this->config->isPreview()) {
            return $this->buildPreviewUri('/generation');
        }

        return $this->config->getBaseUrl() . '/v1/images/generations';
    }

    private function buildEditUri(): string
    {
        if ($this->config->isPreview()) {
            return $this->buildPreviewUri('/edit');
        }

        return $this->config->getBaseUrl() . '/v1/images/edits';
    }

    private function buildRemixUri(): string
    {
        if ($this->config->isPreview()) {
            return $this->buildPreviewUri('/remix');
        }

        return $this->config->getBaseUrl() . '/v1/images/remix';
    }

    private function buildStatusUri(string $taskId): string
    {
        if ($this->config->isPreview()) {
            $projectId = $this->requireProjectId();
            return $this->config->getBaseUrl() . '/api/project/' . rawurlencode($projectId) . '/generation/' . rawurlencode($taskId);
        }

        return $this->config->getBaseUrl() . '/v1/tasks/' . rawurlencode($taskId);
    }

    private function buildPreviewUri(string $suffix): string
    {
        $projectId = $this->requireProjectId();
        return $this->config->getBaseUrl() . '/api/project/' . rawurlencode($projectId) . $suffix;
    }

    private function requireProjectId(): string
    {
        $projectId = $this->config->getProjectId();
        if ($projectId === null || $projectId === '') {
            throw new InvalidArgumentException('Project ID is required for the preview environment.');
        }

        return $projectId;
    }
}
