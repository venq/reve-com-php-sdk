<?php
declare(strict_types=1);

namespace Reve\SDK\Contracts;

use InvalidArgumentException;

final class EditImageRequest
{
    public readonly ImageSource $image;
    public readonly ?MaskSource $mask;
    public readonly string $instruction;
    public readonly ?float $strength;
    /** @var array<string, bool>|null */
    public readonly ?array $preserve;
    public readonly ?int $width;
    public readonly ?int $height;
    public readonly ?int $seed;
    public readonly ?string $model;
    /** @var array<string, scalar>|null */
    public readonly ?array $metadata;

    /**
     * @param array<string, bool>|null $preserve
     * @param array<string, scalar>|null $metadata
     */
    public function __construct(
        ImageSource $image,
        string $instruction,
        ?MaskSource $mask = null,
        ?float $strength = null,
        ?array $preserve = null,
        ?int $width = null,
        ?int $height = null,
        ?int $seed = null,
        ?string $model = null,
        ?array $metadata = null
    ) {
        $instruction = trim($instruction);
        if ($instruction === '') {
            throw new InvalidArgumentException('Instruction must not be empty.');
        }

        if ($strength !== null && ($strength < 0.0 || $strength > 1.0)) {
            throw new InvalidArgumentException('Strength must be between 0 and 1.');
        }

        $this->image = $image;
        $this->mask = $mask;
        $this->instruction = $instruction;
        $this->strength = $strength;
        $this->preserve = $preserve === null ? null : $this->validatePreserve($preserve);
        $this->width = $width;
        $this->height = $height;
        $this->seed = $seed;
        $this->model = $model ? trim($model) : null;
        $this->metadata = $metadata === null ? null : $this->validateMetadata($metadata);
        $this->validateDimensions();
    }

    /**
     * @param array<string, bool> $preserve
     * @return array<string, bool>
     */
    private function validatePreserve(array $preserve): array
    {
        foreach ($preserve as $key => $value) {
            if (!is_bool($value)) {
                throw new InvalidArgumentException('Preserve flags must be boolean. Problematic key: ' . $key);
            }
        }

        return $preserve;
    }

    private function validateDimensions(): void
    {
        if ($this->width !== null && ($this->width < 384 || $this->width > 1024 || $this->width % 8 !== 0)) {
            throw new InvalidArgumentException('Width must be in [384,1024] and divisible by 8.');
        }

        if ($this->height !== null && ($this->height < 384 || $this->height > 1024 || $this->height % 8 !== 0)) {
            throw new InvalidArgumentException('Height must be in [384,1024] and divisible by 8.');
        }
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
        return $this->image->isMultipart() || ($this->mask !== null && $this->mask->isMultipart());
    }

    /**
     * @return array<string, mixed>
     */
    public function toJsonPayload(): array
    {
        $payload = [
            'instruction' => $this->instruction,
        ];

        if ($this->image->appliesAsJson()) {
            $payload += $this->image->toJsonField('image');
        }

        if ($this->mask && $this->mask->appliesAsJson()) {
            $payload += $this->mask->toJsonField('mask');
        }

        if ($this->strength !== null) {
            $payload['strength'] = $this->strength;
        }

        if ($this->width !== null) {
            $payload['width'] = $this->width;
        }

        if ($this->height !== null) {
            $payload['height'] = $this->height;
        }

        if ($this->seed !== null) {
            $payload['seed'] = $this->seed;
        }

        if ($this->model !== null) {
            $payload['model'] = $this->model;
        }

        if ($this->preserve !== null) {
            $payload['preserve'] = $this->preserve;
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
            throw new InvalidArgumentException('Multipart payload requested but not required.');
        }

        $parts = [];

        if ($this->image->isMultipart()) {
            $parts[] = $this->image->toMultipartEntry('image');
        } else {
            $imageField = $this->image->toJsonField('image');
            $name = array_key_first($imageField);
            if ($name === null) {
                throw new InvalidArgumentException('Image JSON payload is empty.');
            }

            $parts[] = [
                'name' => $name,
                'contents' => (string) $imageField[$name],
            ];
        }

        if ($this->mask) {
            if ($this->mask->isMultipart()) {
                $parts[] = $this->mask->toMultipartEntry('mask');
            } else {
                $maskField = $this->mask->toJsonField('mask');
                $name = array_key_first($maskField);
                if ($name === null) {
                    throw new InvalidArgumentException('Mask JSON payload is empty.');
                }

                $parts[] = [
                    'name' => $name,
                    'contents' => (string) $maskField[$name],
                ];
            }
        }

        $parts[] = [
            'name' => 'instruction',
            'contents' => $this->instruction,
        ];

        if ($this->strength !== null) {
            $parts[] = ['name' => 'strength', 'contents' => (string) $this->strength];
        }

        if ($this->width !== null) {
            $parts[] = ['name' => 'width', 'contents' => (string) $this->width];
        }

        if ($this->height !== null) {
            $parts[] = ['name' => 'height', 'contents' => (string) $this->height];
        }

        if ($this->seed !== null) {
            $parts[] = ['name' => 'seed', 'contents' => (string) $this->seed];
        }

        if ($this->model !== null) {
            $parts[] = ['name' => 'model', 'contents' => $this->model];
        }

        if ($this->preserve !== null) {
            foreach ($this->preserve as $key => $value) {
                $parts[] = ['name' => 'preserve[' . $key . ']', 'contents' => $value ? '1' : '0'];
            }
        }

        if ($this->metadata !== null) {
            foreach ($this->metadata as $key => $value) {
                $parts[] = ['name' => 'metadata[' . $key . ']', 'contents' => (string) $value];
            }
        }

        return $parts;
    }
}
