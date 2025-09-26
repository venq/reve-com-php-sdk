# Reve PHP SDK (English)

This guide explains how to install, configure, and use the official PHP SDK for the Reve image platform. The SDK targets PHP 8.4+ and follows PSR-7/17/18 and PSR-3 standards.

## Requirements

- PHP 8.4 or newer with `ext-json`, `ext-mbstring`, `openssl`, and (optional but recommended) `ext-curl`.
- Composer 2.6+.
- Network access to `https://api.reve.com` (official) or `https://preview.reve.art` (preview).

## Installation

```bash
composer require venq/reve-com-php-sdk
```

For local development clone the repository and install dependencies:

```bash
php composer install
```

## Authentication and Environments

Reve exposes two environments:

| Environment | Base URL | Auth headers |
|-------------|----------|--------------|
| official    | `https://api.reve.com` | `Authorization: Bearer <token>` |
| preview     | `https://preview.reve.art` | `Cookie: <session cookie>` and `projectId` query component |

You can configure credentials with environment variables consumed by `ClientConfig::fromEnv()`:

- `REVE_API_BASE` (optional)
- `REVE_AUTHORIZATION`
- `REVE_COOKIE`
- `REVE_PROJECT_ID`

Preview calls require both `REVE_COOKIE` and `REVE_PROJECT_ID`.

## Client Configuration

```php
use Reve\SDK\Config\ClientConfig;

$config = ClientConfig::createOfficial('Bearer sk_live_xxx');
// or preview:
$config = ClientConfig::createPreview('reve_session=abc;', 'proj_123');
```

`ClientConfig` exposes:

- Constructors for official and preview environments.
- Adjustable request timeout (default 30s), connect timeout (default 5s), and GET retry count (default 2).
- `withAuthorization()`, `withCookie()`, `withProjectId()`, and `withBaseUrl()` fluent helpers.
- `getDefaultHeaders()` providing Accept/Content-Type plus auth headers.

## Quick Start

```php
use Reve\SDK\ReveClient;
use Reve\SDK\Contracts\CreateImageRequest;

$client = ReveClient::createDefault();

$response = $client->createImage(new CreateImageRequest(
    prompt: 'a still life scene with ceramics and soft sunlight',
    width: 768,
    height: 768,
));

$final = $client->getPolling()->waitUntilCompleted($response->taskId);
foreach ($final->imageUrls as $url) {
    echo $url . PHP_EOL;
}
```

## Generations (Text-to-Image)

- `CreateImageRequest` validates dimensions (384-1024, step 8), batch size (1..4), and metadata scalar values.
- `ReveClient::createImage()` issues `POST /v1/images/generations` (official) or `/api/project/{projectId}/generation` (preview).
- The response is a `CreateImageResponse` with `taskId` and optional warnings.
- Poll `ReveClient::getPolling()->waitUntilCompleted()` for completion.

### DTO Reference

- `CreateImageRequest` fields: `prompt`, `negativePrompt`, `width`, `height`, `batchSize`, `seed`, `model`, `enhancePrompt`, `metadata`.
- `CompletedGeneration` includes final URLs, seed, prompts, applied instruction, metadata, and completion timestamp.
- `GenerationStatus` contains `TaskStatus`, estimated wait, credit usage, warnings, and the optional result payload.

## Edits

Use `EditImageRequest` with an `ImageSource` and optional `MaskSource`.

- `ImageSource` supports `fromFile`, `fromStream`, `fromUrl`, and `fromDataUrl`.
- Multipart uploads are selected automatically when a file/stream source is present.
- JSON payloads are generated when you work with URLs/data URLs only.
- `strength` (0..1), custom dimensions, metadata, and `preserve[...]` modifiers are supported.

Example:

```php
use Reve\SDK\Contracts\EditImageRequest;
use Reve\SDK\Contracts\ImageSource;
use Reve\SDK\Contracts\MaskSource;

$editRequest = new EditImageRequest(
    image: ImageSource::fromFile(__DIR__ . '/product.png'),
    instruction: 'replace the background with matte white and boost overall exposure',
    mask: MaskSource::fromFile(__DIR__ . '/mask.png'),
    strength: 0.55,
);
$task = $client->editImage($editRequest);
```

## Remix

`RemixRequest` allows you to push stylistic changes to an existing image:

```php
use Reve\SDK\Contracts\RemixRequest;

$remix = $client->remix(new RemixRequest(
    image: ImageSource::fromUrl($final->imageUrls[0]),
    prompt: 'art deco poster treatment, warm gold accents',
    variation: 0.4,
    batchSize: 3,
));
```

## Polling Client

`PollingClient::waitUntilCompleted(string $taskId, int $intervalSeconds = 2, int $timeoutSeconds = 300)` polls status:

- honours `Retry-After` headers,
- falls back to server-provided `estimated_wait_seconds`,
- throws `PollingException` on timeout or failed status.

Inject a custom `SleeperInterface` to control wait behaviour for tests.

## Error Handling

HTTP errors map to domain exceptions:

- `BadRequestException` (400)
- `UnauthorizedException` (401/403)
- `TooManyRequestsException` (429)
- `ServerException` (>=500)
- `HttpException` (unexpected status)
- `SerializationException` for JSON encoding/decoding issues

All exceptions extend `ReveException`.

## Logging

The SDK accepts any PSR-3 logger. By default a `NullLogger` is used. Failed GET retries emit `warning`; polling emits `debug` entries.

## Testing and Tooling

Composer scripts:

```bash
php composer test  # phpunit
php composer stan  # phpstan analyse
php composer lint  # phpcs --standard=PSR12
```

The test suite ships with unit tests for DTO validation and polling logic. PHPStan level 6 is configured via `phpstan.neon.dist`.

## Examples

- `examples/basic.php` demonstrates end-to-end generation, polling, and remix.
- `examples/two-image-outfit.php` shows how to keep the first image as the base model and borrow garments from a second reference via an edit task.
- Use `ImageSource::fromStream()` for in-memory buffers when integrating with frameworks.

## Troubleshooting

| Symptom | Resolution |
|---------|------------|
| `TooManyRequestsException` | Respect rate limits and retry after the indicated delay. |
| `Project ID is required for the preview environment.` | Set `REVE_PROJECT_ID` when targeting preview. |
| Composer slow downloads | Enable the PHP curl extension. |
| Empty task result despite `completed` | The SDK raises `ServerException`; capture logs and contact Reve support. |

## Support

Questions and bug reports can be filed through the Reve developer portal or the SDK issue tracker.
