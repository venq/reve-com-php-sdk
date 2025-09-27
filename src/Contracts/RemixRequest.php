<?php

declare(strict_types=1);

namespace Reve\SDK\Contracts;

use InvalidArgumentException;

final class RemixRequest
{
    public readonly ImageSource $image;
    public readonly ?string $prompt;
    public readonly ?float $variation;
    public readonly ?string $style;
    public readonly ?string $composition;
    public readonly ?int $batchSize;
    public readonly ?int $seed;
    public readonly ?string $model;
    /** @var array<string, scalar>|null */
    public readonly ?array $metadata;

    /**
     * @param array<string, scalar>|null $metadata
     */
    public function __construct(
        ImageSource $image,
        ?string $prompt = null,
        ?float $variation = null,
        ?string $style = null,
        ?string $composition = null,
        ?int $batchSize = null,
        ?int $seed = null,
        ?string $model = null,
        ?array $metadata = null
    ) {
        if ($variation !== null && ($variation < 0.0 || $variation > 1.0)) {
            throw new InvalidArgumentException('Variation must be between 0 and 1.');
        }

        if ($batchSize !== null && ($batchSize < 1 || $batchSize > 4)) {
            throw new InvalidArgumentException('Batch size must be between 1 and 4.');
        }

        $this->image = $image;
        $this->prompt = $prompt !== null ? trim($prompt) : null;
        $this->variation = $variation;
        $this->style = $style !== null ? trim($style) : null;
        $this->composition = $composition !== null ? trim($composition) : null;
        $this->batchSize = $batchSize;
        $this->seed = $seed;
        $this->model = $model !== null ? trim($model) : null;
        $this->metadata = $metadata === null ? null : $this->validateMetadata($metadata);
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

    public function usesMultipart(): bool
    {
        return $this->image->isMultipart();
    }

    /**
     * @return array<string, mixed>
     */
    public function toJsonPayload(): array
    {
        $payload = [];

        if ($this->image->appliesAsJson()) {
            $payload += $this->image->toJsonField('image');
        }

        if ($this->prompt !== null && $this->prompt !== '') {
            $payload['prompt'] = $this->prompt;
        }

        if ($this->variation !== null) {
            $payload['variation'] = $this->variation;
        }

        if ($this->style !== null && $this->style !== '') {
            $payload['style'] = $this->style;
        }

        if ($this->composition !== null && $this->composition !== '') {
            $payload['composition'] = $this->composition;
        }

        if ($this->batchSize !== null) {
            $payload['batch_size'] = $this->batchSize;
        }

        if ($this->seed !== null) {
            $payload['seed'] = $this->seed;
        }

        if ($this->model !== null && $this->model !== '') {
            $payload['model'] = $this->model;
        }

        if ($this->metadata !== null) {
            $payload['metadata'] = $this->metadata;
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toMultipartPayload(): array
    {
        if (!$this->usesMultipart()) {
            throw new InvalidArgumentException('Multipart payload requested but image is not multipart.');
        }

        $parts = [$this->image->toMultipartEntry('image')];

        if ($this->prompt !== null && $this->prompt !== '') {
            $parts[] = ['name' => 'prompt', 'contents' => $this->prompt];
        }

        if ($this->variation !== null) {
            $parts[] = ['name' => 'variation', 'contents' => (string) $this->variation];
        }

        if ($this->style !== null && $this->style !== '') {
            $parts[] = ['name' => 'style', 'contents' => $this->style];
        }

        if ($this->composition !== null && $this->composition !== '') {
            $parts[] = ['name' => 'composition', 'contents' => $this->composition];
        }

        if ($this->batchSize !== null) {
            $parts[] = ['name' => 'batch_size', 'contents' => (string) $this->batchSize];
        }

        if ($this->seed !== null) {
            $parts[] = ['name' => 'seed', 'contents' => (string) $this->seed];
        }

        if ($this->model !== null && $this->model !== '') {
            $parts[] = ['name' => 'model', 'contents' => $this->model];
        }

        if ($this->metadata !== null) {
            foreach ($this->metadata as $key => $value) {
                $parts[] = ['name' => 'metadata[' . $key . ']', 'contents' => (string) $value];
            }
        }

        return $parts;
    }
}
