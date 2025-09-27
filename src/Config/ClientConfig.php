<?php

declare(strict_types=1);

namespace Reve\SDK\Config;

use Reve\SDK\Enums\Environment;

final class ClientConfig
{
    private const DEFAULT_BASE_URL = 'https://api.reve.com';
    private const DEFAULT_PREVIEW_BASE_URL = 'https://preview.reve.art';

    private string $baseUrl;
    private ?string $authorization;
    private ?string $cookie;
    private ?string $projectId;
    private float $timeout;
    private float $connectTimeout;
    private int $maxGetRetries;
    private Environment $environment;

    public function __construct(
        ?string $baseUrl = null,
        ?string $authorization = null,
        ?string $cookie = null,
        ?string $projectId = null,
        float $timeout = 30.0,
        float $connectTimeout = 5.0,
        int $maxGetRetries = 2
    ) {
        $this->baseUrl = rtrim($baseUrl ?? self::DEFAULT_BASE_URL, '/');
        $this->authorization = $authorization;
        $this->cookie = $cookie;
        $this->projectId = $projectId;
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->maxGetRetries = max(0, $maxGetRetries);
        $this->environment = str_contains($this->baseUrl, 'preview.reve.art')
            ? Environment::Preview
            : Environment::Official;
    }

    public static function createOfficial(?string $token = null): self
    {
        return new self(self::DEFAULT_BASE_URL, $token, null, null);
    }

    public static function createPreview(?string $cookie = null, ?string $projectId = null): self
    {
        return new self(self::DEFAULT_PREVIEW_BASE_URL, null, $cookie, $projectId);
    }

    public static function fromEnv(): self
    {
        $base = getenv('REVE_API_BASE') ?: null;
        $authorization = getenv('REVE_AUTHORIZATION') ?: null;
        $cookie = getenv('REVE_COOKIE') ?: null;
        $projectId = getenv('REVE_PROJECT_ID') ?: null;

        return new self(
            $base ?: null,
            $authorization ?: null,
            $cookie ?: null,
            $projectId ?: null
        );
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getAuthorization(): ?string
    {
        return $this->authorization;
    }

    public function withAuthorization(?string $token): self
    {
        $clone = clone $this;
        $clone->authorization = $token;
        return $clone;
    }

    public function getCookie(): ?string
    {
        return $this->cookie;
    }

    public function withCookie(?string $cookie): self
    {
        $clone = clone $this;
        $clone->cookie = $cookie;
        return $clone;
    }

    public function getProjectId(): ?string
    {
        return $this->projectId;
    }

    public function withProjectId(?string $projectId): self
    {
        $clone = clone $this;
        $clone->projectId = $projectId;
        return $clone;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function getConnectTimeout(): float
    {
        return $this->connectTimeout;
    }

    public function getMaxGetRetries(): int
    {
        return $this->maxGetRetries;
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function isPreview(): bool
    {
        return $this->environment->isPreview();
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->authorization) {
            $headers['Authorization'] = $this->authorization;
        }

        if ($this->cookie) {
            $headers['Cookie'] = $this->cookie;
        }

        return $headers;
    }

    public function withBaseUrl(string $baseUrl): self
    {
        $clone = clone $this;
        $clone->baseUrl = rtrim($baseUrl, '/');
        $clone->environment = str_contains($clone->baseUrl, 'preview.reve.art')
            ? Environment::Preview
            : Environment::Official;
        return $clone;
    }
}
