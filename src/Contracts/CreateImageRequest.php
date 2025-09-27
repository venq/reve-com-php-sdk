<?php

declare(strict_types=1);

namespace Reve\SDK\Contracts;

use InvalidArgumentException;

final class CreateImageRequest
{
    private const MIN_DIMENSION = 384;
    private const MAX_DIMENSION = 1024;
    private const DIMENSION_STEP = 8;

    public readonly string $prompt;
    public readonly ?string $negativePrompt;
    public readonly int $width;
    public readonly int $height;
    public readonly int $batchSize;
    public readonly ?int $seed;
    public readonly ?string $model;
    public readonly bool $enhancePrompt;
    /** @var array<string, scalar>|null */
    public readonly ?array $metadata;

    /**
     * @param array<string, scalar>|null $metadata
     */
    public function __construct(
        string $prompt,
        ?string $negativePrompt = null,
        int $width = self::MAX_DIMENSION,
        int $height = self::MAX_DIMENSION,
        int $batchSize = 1,
        ?int $seed = -1,
        ?string $model = null,
        bool $enhancePrompt = true,
        ?array $metadata = null
    ) {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new InvalidArgumentException('Prompt must not be empty.');
        }

        $this->prompt = $prompt;
        $this->negativePrompt = $negativePrompt ? trim($negativePrompt) : null;
        $this->width = $this->validateDimension($width, 'width');
        $this->height = $this->validateDimension($height, 'height');
        $this->batchSize = $this->validateBatchSize($batchSize);
        $this->seed = $seed;
        $this->model = $model ? trim($model) : null;
        $this->enhancePrompt = $enhancePrompt;
        $this->metadata = $metadata === null ? null : $this->validateMetadata($metadata);
    }

    private function validateDimension(int $dimension, string $name): int
    {
        if ($dimension < self::MIN_DIMENSION || $dimension > self::MAX_DIMENSION) {
            throw new InvalidArgumentException(sprintf(
                '%s must be between %d and %d.',
                ucfirst($name),
                self::MIN_DIMENSION,
                self::MAX_DIMENSION
            ));
        }

        if ($dimension % self::DIMENSION_STEP !== 0) {
            throw new InvalidArgumentException(ucfirst($name) . ' must be divisible by ' . self::DIMENSION_STEP . '.');
        }

        return $dimension;
    }

    private function validateBatchSize(int $batchSize): int
    {
        if ($batchSize < 1 || $batchSize > 4) {
            throw new InvalidArgumentException('Batch size must be between 1 and 4.');
        }

        return $batchSize;
    }

    /**
     * @param array<string, scalar> $metadata
     * @return array<string, scalar>
     */
    private function validateMetadata(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (!is_scalar($value)) {
                throw new InvalidArgumentException('Metadata values must be scalar. Problematic key: ' . $key);
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [
            'prompt' => $this->prompt,
            'width' => $this->width,
            'height' => $this->height,
            'batch_size' => $this->batchSize,
            'enhance_prompt' => $this->enhancePrompt,
        ];

        if ($this->negativePrompt !== null && $this->negativePrompt !== '') {
            $payload['negative_prompt'] = $this->negativePrompt;
        }

        if ($this->seed !== null) {
            $payload['seed'] = $this->seed;
        }

        if ($this->model !== null) {
            $payload['model'] = $this->model;
        }

        if ($this->metadata !== null) {
            $payload['metadata'] = $this->metadata;
        }

        return $payload;
    }
}
