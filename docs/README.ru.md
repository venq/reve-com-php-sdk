# Reve PHP SDK (Русский)

Это руководство описывает установку, настройку и использование официального PHP SDK для платформы Reve. Библиотека рассчитана на PHP 8.4+ и соответствует стандартам PSR-7/17/18 и PSR-3.

## Требования

- PHP 8.4 или новее с расширениями `ext-json`, `ext-mbstring`, `openssl` и (желательно) `ext-curl`.
- Composer 2.6+.
- Сетевой доступ к `https://api.reve.com` (production) или `https://preview.reve.art` (preview).

## Установка

```bash
composer require venq/reve-com-php-sdk
```

Для локальной разработки:

```bash
php composer install
```

## Аутентификация и окружения

SDK поддерживает два окружения Reve:

| Окружение | Базовый URL | Авторизация |
|-----------|-------------|-------------|
| official  | `https://api.reve.com` | заголовок `Authorization: Bearer <token>` |
| preview   | `https://preview.reve.art` | cookie сессии и `projectId` в пути |

Переменные окружения для `ClientConfig::fromEnv()`:

- `REVE_API_BASE` (необязательно)
- `REVE_AUTHORIZATION`
- `REVE_COOKIE`
- `REVE_PROJECT_ID`

Для preview обязательны `REVE_COOKIE` и `REVE_PROJECT_ID`.

## Конфигурация клиента

```php
use Reve\SDK\Config\ClientConfig;

$config = ClientConfig::createOfficial('Bearer sk_live_xxx');
// или preview
$config = ClientConfig::createPreview('reve_session=abc;', 'proj_123');
```

Возможности `ClientConfig`:

- Быстрые фабрики для official/preview.
- Настройка таймаутов запроса (по умолчанию 30s), соединения (5s) и числа повторов GET (2).
- Методы `withAuthorization()`, `withCookie()`, `withProjectId()`, `withBaseUrl()` возвращают клон с изменением.
- `getDefaultHeaders()` автоматически добавляет `Accept`, `Content-Type` и авторизационные данные.

## Быстрый старт

```php
use Reve\SDK\ReveClient;
use Reve\SDK\Contracts\CreateImageRequest;

$client = ReveClient::createDefault();

$response = $client->createImage(new CreateImageRequest(
    prompt: 'минималистичная натюрмортная сцена с керамикой и мягким светом',
    width: 768,
    height: 768,
));

$final = $client->getPolling()->waitUntilCompleted($response->taskId);
foreach ($final->imageUrls as $url) {
    echo $url . PHP_EOL;
}
```

## Генерации (Text-to-Image)

- `CreateImageRequest` проверяет допустимые размеры (384-1024, шаг 8), размер батча (1..4) и типы метаданных.
- `ReveClient::createImage()` вызывает `POST /v1/images/generations` (official) или `/api/project/{projectId}/generation` (preview).
- Ответ — `CreateImageResponse` с `taskId` и опциональными предупреждениями.
- Для получения результата используйте `PollingClient`.

### DTO

- `CreateImageRequest`: `prompt`, `negativePrompt`, `width`, `height`, `batchSize`, `seed`, `model`, `enhancePrompt`, `metadata`.
- `CompletedGeneration`: ссылки на изображения, seed, итоговые подсказки, применённую инструкцию, метаданные, время завершения.
- `GenerationStatus`: статус (`TaskStatus`), оценка ожидания, кредиты, предупреждения, опциональный результат.

## Редактирование (Edit)

`EditImageRequest` принимает основной `ImageSource` и опциональную маску `MaskSource`.

- `ImageSource::fromFile()`, `fromStream()`, `fromUrl()`, `fromDataUrl()`.
- При использовании файлов/потоков формируется multipart; ссылки и data URL отправляются JSON-ом.
- Поддерживаются `strength` (0..1), кастомные размеры, `preserve[...]`, `metadata`.

Пример:

```php
use Reve\SDK\Contracts\EditImageRequest;
use Reve\SDK\Contracts\ImageSource;
use Reve\SDK\Contracts\MaskSource;

$editRequest = new EditImageRequest(
    image: ImageSource::fromFile(__DIR__ . '/product.png'),
    instruction: 'заменить фон на белый и усилить освещение',
    mask: MaskSource::fromFile(__DIR__ . '/mask.png'),
    strength: 0.55,
);
$task = $client->editImage($editRequest);
```

## Remix

`RemixRequest` применяет стилистические изменения к готовому изображению:

```php
use Reve\SDK\Contracts\RemixRequest;

$remix = $client->remix(new RemixRequest(
    image: ImageSource::fromUrl($final->imageUrls[0]),
    prompt: 'арт-деко постер, тёплые золотые акценты',
    variation: 0.4,
    batchSize: 3,
));
```

## PollingClient

`waitUntilCompleted(string $taskId, int $intervalSeconds = 2, int $timeoutSeconds = 300)`:

- учитывает заголовок `Retry-After`,
- использует `estimated_wait_seconds`, если доступно,
- бросает `PollingException` при таймауте либо статусе `failed`.

Для тестов можно внедрить собственный `SleeperInterface`.

## Обработка ошибок

Исключения:

- `BadRequestException` (400)
- `UnauthorizedException` (401/403)
- `TooManyRequestsException` (429)
- `ServerException` (>=500)
- `HttpException` (прочие коды)
- `SerializationException` (ошибка JSON)

Все наследуются от `ReveException`.

## Логирование

По умолчанию используется `NullLogger`, но можно передать любой PSR-3 логгер. Повторы GET логируются как `warning`, polling — как `debug`.

## Тесты и инструменты

Скрипты Composer:

```bash
php composer test  # PHPUnit
php composer stan  # PHPStan
php composer lint  # PHP_CodeSniffer (PSR12)
```

В комплект входят unit-тесты DTO и логики polling, а также конфигурация PHPStan (уровень 6).

## Примеры

- `examples/basic.php` — полный цикл: генерация, ожидание, remix.
- `examples/two-image-outfit.php` — пример комбинирования двух изображений: базовое фото модели и референс одежды для редактирования.
- `ImageSource::fromStream()` полезен для интеграций без файловой системы.

## Диагностика

| Симптом | Решение |
|---------|---------|
| `TooManyRequestsException` | Соблюдайте лимиты, повторяйте запрос после задержки. |
| `Project ID is required for the preview environment.` | Задайте `REVE_PROJECT_ID` и cookie для preview. |
| Медленные загрузки Composer | Включите расширение PHP curl. |
| Статус `completed`, но нет `image_urls` | SDK генерирует `ServerException`; сохраните логи и обратитесь в поддержку Reve. |

## Поддержка

Вопросы и отчёты об ошибках направляйте через портал разработчиков Reve или трекер ошибок SDK.
