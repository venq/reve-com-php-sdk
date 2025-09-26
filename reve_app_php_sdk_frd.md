# Reve PHP SDK — Функциональные требования (FRD)
*(версия 2025-09-25 / PHP 8.4+)*

> Документ описывает поведение SDK для **Reve API**: генерация, **редактирование (Edit)** и **ремикс (Remix)** изображений, включая поллинг и устойчивость к beta-изменениям API. Основан на нашем проверенном шаблоне FRD и текущей публичной информации о Reve (консоль `api.reve.com`, предпросмотр `preview.reve.art`).

---

## 1. Область продукта
- Продакшен‑готовый SDK для **PHP 8.4+** с поддержкой PSR‑18/17/3.
- Сценарии:
  1) **Text‑to‑Image** (генерация).
  2) **Edit** — правки существующего изображения: локальные/глобальные текстовые правки, маски, in‑painting/out‑painting.
  3) **Remix** — вариации/перекомпоновка существующего изображения на основе нового текста/стиля/кадровки.
- Поддержка двух профилей окружений: **official** (`https://api.reve.com`) и **preview** (`https://preview.reve.art`).

## 2. Поставка и стандарты
- Composer: `venq/reve-com-php-sdk`, namespace: `Reve\SDK\`.
- PSR‑12, `declare(strict_types=1)`.
- Зависимости: `psr/http-client`, `psr/http-message`, `psr/log`, `guzzlehttp/guzzle`.
- Dev: `phpunit/phpunit`, `phpstan/phpstan`.
- Скрипты: `composer test`, `composer stan`, `composer lint`.

## 3. Архитектура
```
/src
  /Config
  /Contracts      # DTO/VO/Interfaces
  /Enums
  /Exceptions
  /Http           # PSR-18 адаптер, ретраи, backoff
  ReveClient.php
  PollingClient.php
/docs
/examples
/tests
```
- Без глобальной статики; конфигурация инжектируется.

## 4. Конфигурация (ClientConfig)
- `baseUrl`: по умолчанию `https://api.reve.com`; допускается `https://preview.reve.art`.
- Таймауты: общий 30s, connect 5s (по умолчанию).
- Ретраи для **GET**: до 2, экспоненциальный backoff + джиттер.
- Заголовки: `Accept: application/json`, `Content-Type: application/json`.
- Авторизация: `Authorization: Bearer <token>`; при профиле preview — поддержка `Cookie` + `projectId`.
- Метод `fromEnv()` читает: `REVE_API_BASE`, `REVE_AUTHORIZATION`, `REVE_COOKIE`, `REVE_PROJECT_ID`.

## 5. HTTP-слой
- `Http\ClientInterface` + реализация `Http\Psr18Client` (Guzzle).
- Поведение: `http_errors=false`, маппинг статусов на исключения, чтение `Retry-After`.

## 6. Доменные контракты (DTO/VO)

### 6.1. Общие
- **ImageSource**: {{
  type: `'url'|'data-url'|'filepath'|'stream'`,
  value: `string|resource`,
  mime?: `image/png|image/jpeg|image/webp`
}}
- **MaskSource**: такие же типы как ImageSource; опциональна.

### 6.2. Генерация (Text-to-Image)
- **CreateImageRequest**
  - `prompt` (string, required)
  - `negativePrompt` (string|null)
  - `width`/`height` (int; 384–1024, кратно 8; дефолт 1024)
  - `batchSize` (1..4; дефолт 1)
  - `seed` (int; −1 = random)
  - `model` (string|null)
  - `enhancePrompt` (bool; дефолт `true`)
  - `metadata` (array<string, scalar>|null)
- **CreateImageResponse**
  - `taskId` (string), `warnings` (string[]|null)

- **GenerationStatus** (общее для всех задач)
  - `status` (`queued|running|completed|failed`)
  - `estimatedWaitSeconds` (int|null)
  - `credits` (int|null)
  - `warnings` (string[]|null)

- **CompletedGeneration**
  - `imageUrls` (string[]) — URL или data‑URL/base64
  - `seed` (int|null), `completedAt` (DateTimeImmutable|null)
  - `prompt` (string), `enhancedPrompt` (string|null), `enhancedPrompts` (string[]|null)
  - `meta` (array|null)

### 6.3. **Edit** (правка изображения)
- **EditImageRequest**
  - `image` (**ImageSource**, required) — исходное изображение.
  - `instruction` (string, required) — текст правки (например: «удали лишний объект», «поменяй фон на белый»).
  - `mask` (**MaskSource**|null) — прозрачные области редактируются (in‑painting); без маски — глобальная правка.
  - `strength` (float 0..1|null) — сила воздействия (дефолт 0.6).
  - `preserve` (array<string,bool>|null) — флаги «сохранять стиль/композицию/цветокор».
  - `width`/`height` (int|null) — при out‑painting/кадровке.
  - `seed` (int|null), `model` (string|null), `metadata` (array|null).
- **EditImageResponse**
  - `taskId` (string), `warnings` (string[]|null).

- **CompletedEdit**
  - Те же поля, что у CompletedGeneration (+ `appliedInstruction` (string)).

### 6.4. **Remix** (вариации на основе исходного изображения)
- **RemixRequest**
  - `image` (**ImageSource**, required).
  - `prompt` (string|null) — новый/добавочный текст (например: «в стиле арт‑деко»).
  - `variation` (float 0..1|null) — степень отклонения от исходника (дефолт 0.5).
  - `style` (string|null) — пресет/ярлык стиля, если поддерживается.
  - `composition` (string|null) — «кадрирование/ракурс», если поддерживается.
  - `batchSize` (int 1..4|null), `seed` (int|null), `model` (string|null), `metadata` (array|null).
- **RemixResponse**
  - `taskId` (string), `warnings` (string[]|null).

- **CompletedRemix**
  - Аналогично CompletedGeneration + `sourceFingerprint` (string|null).

## 7. Enums
- `Status`: `queued|running|completed|failed`
- `FailureReason`: `rate_limited|invalid_input|unauthorized|server_error|unknown`
- `Profile`: `official|preview` (маршрутизация)
- `ImageType`: `png|jpeg|webp`

## 8. Исключения
- `ReveException` (сообщение, httpStatus, payload, response).
- `BadRequestException(400)`, `UnauthorizedException(401)`, `ForbiddenException(403)`,
  `NotFoundException(404)`, `UnprocessableException(422)`, `TooManyRequestsException(429)` (с `Retry-After`),
  `ServerException(5xx)`.
- Категории для логирования: `AUTHENTICATION_ERROR|API_ERROR|REQUEST_ERROR|TIMEOUT_ERROR|GENERATION_ERROR|POLLING_ERROR|UNEXPECTED_RESPONSE|UNKNOWN_ERROR`.

## 9. Клиенты SDK

### 9.1. `ReveClient`
- `createImage(CreateImageRequest): CreateImageResponse`
- `getGenerationStatus(string $taskId): Pending|CompletedGeneration`
- **`editImage(EditImageRequest): EditImageResponse`**
- `getEditStatus(string $taskId): Pending|CompletedEdit`
- **`remix(RemixRequest): RemixResponse`**
- `getRemixStatus(string $taskId): Pending|CompletedRemix`

Общее:
- POST‑вызовы без ретраев; GET — с ретраями (429/5xx/сеть).
- Нормализация полей (id/taskId, url/data‑url).
- Debug‑режим — логирует тела запросов/ответов.

### 9.2. `PollingClient`
- `waitUntilCompleted(string $taskId, int $intervalSeconds=2, int $timeoutSeconds=300): Completed*`
- Учитывает `Retry-After` и `estimatedWaitSeconds`.
- По таймауту — `ReveException(POLLING_ERROR)`.

## 10. Маршрутизация (профили)

> **Важно:** Reve API — beta; в проде могут отличаться маршруты/схемы. SDK поддерживает **два профиля** с возможностью расширения.

### 10.1. `official` (`https://api.reve.com`)
- **Генерация**: `POST /v1/images/generations` (JSON). *(Если официальный reference подтвердит другой путь — меняем в конфиге профиля.)*
- **Статус**: `GET /v1/tasks/{{taskId}}`.
- **Edit**: `POST /v1/images/edits` — multipart (изображение/маска) или JSON с URL.
- **Remix**: `POST /v1/images/remix` — multipart (изображение) или JSON с URL.

### 10.2. `preview` (`https://preview.reve.art`)
- **Генерация**: `POST /api/project/{{projectId}}/generation` (JSON).
- **Статус генерации**: `GET /api/project/{{projectId}}/generation/{{id}}`.
- **Edit**: `POST /api/project/{{projectId}}/edit` — multipart/JSON (см. 11).
- **Remix**: `POST /api/project/{{projectId}}/remix` — multipart/JSON (см. 11).

> Конкретные пути для `edit/remix` в preview могут отличаться; SDK хранит их в конфиге профиля и позволяет переопределить без релиза.

## 11. Кодирование запросов и загрузка файлов
- **JSON**: для генерации и сценариев, где передаются URL/параметры.
- **multipart/form-data**: для `edit/remix` с локальными файлами/потоками.
- Авто‑детект источника: `filepath` → multipart; `stream` → multipart; `url|data-url` → JSON.
- Поддержка PSR‑7 Stream для загрузок.
- Проверка MIME/размера (конфигурируемые лимиты).

## 12. Логирование
- PSR‑3 Logger (по умолчанию NullLogger).
- `warning`: лимиты, деградация, нестандартные поля.
- `debug`: тела запросов/ответов (по флагу).

## 13. Тестирование
- PHPUnit: валидация DTO, маппинг ошибок, ретраи GET, поллинг, tolerant‑парсинг (url vs data‑url), multipart.
- Моки: фейковый HTTP‑клиент, `SleeperInterface` вместо `sleep()` для тестов.

## 14. Нефункциональные требования
- Устойчивость к изменению схемы (tolerant‑parser).
- Отбрасывание `null`/пустых значений в payload/headers.
- Исключения с полным контекстом (requestId/traceId, если приходит).

## 15. Вне объёма (MVP) / Дальше
- Вариации/апскейл/ретушь‑плагины как отдельные команды (когда появится reference).
- Управление биллингом/кредитами через SDK.
- Laravel/Symfony провайдеры (после стабилизации API).

## 16. Провайдеры
- Laravel провайдер, с описанием использования на английском и русском языках

---

# Приложения

## A. Правила валидации параметров
- `width/height` ∈ [384..1024], кратно 8 (дефолты 1024).
- `batchSize` ∈ [1..4].
- `strength` ∈ [0..1].
- `variation` ∈ [0..1].

## B. Псевдо‑контракты HTTP (официальный профиль)

### B.1. POST /v1/images/generations (JSON)
Request:
```json
{{
  "prompt": "string",
  "negative_prompt": "string|null",
  "width": 1024,
  "height": 1024,
  "batch_size": 1,
  "seed": -1,
  "model": "string|null",
  "enhance_prompt": true,
  "metadata": {{}}
}}
```
Response (201):
```json
{{ "task_id": "string" }}
```

### B.2. POST /v1/images/edits (multipart | JSON)
**multipart form-data поля**:
- `image`: файл (png/jpeg/webp) — обязательно.
- `mask`: файл (png/webp с альфа‑каналом) — опционально.
- `instruction`: строка — обязательно.
- `strength`, `width`, `height`, `seed`, `model`, `metadata` — опционально.

**JSON** (если `image`/`mask` — по URL):
```json
{{
  "image_url": "https://...",
  "mask_url": "https://...",
  "instruction": "remove the watermark and brighten the scene",
  "strength": 0.6,
  "width": 1024,
  "height": 1024,
  "seed": -1,
  "model": "text2image_v1/...",
  "metadata": {{ "source": "cms-42" }}
}}
```

### B.3. POST /v1/images/remix (multipart | JSON)
Поля:
- `image` (файл или `image_url`) — обязательно.
- `prompt` — опционально (добавочная трактовка).
- `variation` (0..1) — степень отклонения.
- `style`, `composition` — опционально.
- `batch_size`, `seed`, `model`, `metadata`.

### B.4. GET /v1/tasks/{{taskId}}
Response (200):
```json
{{
  "status": "queued|running|completed|failed",
  "estimated_wait_seconds": 12,
  "result": {{
    "image_urls": ["https://... or data:..."],
    "seed": 1234,
    "completed_at": "2025-09-25T20:00:00Z",
    "prompt": "string",
    "enhanced_prompt": "string|null",
    "enhanced_prompts": ["..."],
    "applied_instruction": "string|null",
    "meta": {{}}
  }},
  "warnings": ["..."]
}}
```

## C. Пример использования SDK

```php
use Reve\SDK\ReveClient;
use Reve\SDK\Contracts\CreateImageRequest;
use Reve\SDK\Contracts\EditImageRequest;
use Reve\SDK\Contracts\RemixRequest;

// 1) Инициализация клиента (default из ENV)
$client = ReveClient::createDefault();

// 2) Генерация
$gen = $client->createImage(new CreateImageRequest(
  prompt: 'a product photo of minimalist sneakers on white acrylic blocks, soft studio light',
  width: 1024,
  height: 1024,
));
$done = $client->getPolling()->waitUntilCompleted($gen->taskId);

// 3) Edit — удаляем фон и добавляем мягкую подсветку
$edit = $client->editImage(new EditImageRequest(
  image: ImageSource::fromFile('/path/to/shoe.jpg'),
  instruction: 'replace background with pure white, add soft rim light to edges',
  strength: 0.6,
));
$editDone = $client->getPolling()->waitUntilCompleted($edit->taskId);

// 4) Remix — делаем вариации в стиле арт‑деко
$remix = $client->remix(new RemixRequest(
  image: ImageSource::fromUrl($editDone->imageUrls[0]),
  prompt: 'art-deco poster style, bold geometry, gold accents',
  variation: 0.45,
  batchSize: 3,
));
$remixDone = $client->getPolling()->waitUntilCompleted($remix->taskId);
```

## D. Тест‑кейс матрица (фрагмент)
- Edit без маски → глобальная правка; c mask → in‑painting.
- Edit с `strength=0` → near‑identity (минимальные изменения).
- Remix с `variation=1` → сильные отличия; `0` → почти исходник.
- Ошибки загрузки (несоответствие MIME/размер) → `BadRequestException`.
- 429 с `Retry-After` → ожидание и повтор (GET).
- Статус `completed` без валидных `image_urls` → `ServerException`.

---

## E. План внедрения
1. Каркас репозитория + конфигурация профилей/роутинга.
2. Реализация `CreateImage`, `Status`, затем `Edit`, `Remix`.
3. Multipart‑обёртки: `ImageSource/MaskSource` + авто‑детект.
4. Поллинг и ретраи.
5. Документация и примеры.
6. Тесты (unit + интеграционные фейки).

---

**Примечание.** Ввиду beta‑статуса официальной документации, имена и точные пути эндпоинтов для `Edit/Remix` могут отличаться в финальном reference. SDK спроектирован так, чтобы перенастроить маршруты/поля через конфиг профиля без изменений публичного API SDK.
