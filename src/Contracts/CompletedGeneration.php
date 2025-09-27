<?php

declare(strict_types=1);

namespace Reve\SDK\Contracts;

use DateTimeImmutable;
use InvalidArgumentException;

final class CompletedGeneration
{
    /**
     * @param string[] $imageUrls
     * @param string[]|null $enhancedPrompts
     * @param array<string, mixed>|null $meta
     */
    public function __construct(
        public readonly array $imageUrls,
        public readonly ?int $seed,
        public readonly ?DateTimeImmutable $completedAt,
        public readonly string $prompt,
        public readonly ?string $enhancedPrompt,
        public readonly ?array $enhancedPrompts,
        public readonly ?string $appliedInstruction,
        public readonly ?array $meta
    ) {
        if ($prompt === '') {
            throw new InvalidArgumentException('Prompt cannot be empty.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $imageUrls = [];
        if (isset($payload['image_urls']) && is_array($payload['image_urls'])) {
            foreach ($payload['image_urls'] as $url) {
                $imageUrls[] = (string) $url;
            }
        }

        $completedAt = null;
        if (!empty($payload['completed_at'])) {
            $completedAt = new DateTimeImmutable((string) $payload['completed_at']);
        }

        $enhancedPrompts = null;
        if (isset($payload['enhanced_prompts']) && is_array($payload['enhanced_prompts'])) {
            $enhancedPrompts = array_map('strval', $payload['enhanced_prompts']);
        }

        $meta = null;
        if (isset($payload['meta']) && is_array($payload['meta'])) {
            $meta = $payload['meta'];
        }

        return new self(
            $imageUrls,
            isset($payload['seed']) ? (int) $payload['seed'] : null,
            $completedAt,
            (string) ($payload['prompt'] ?? ''),
            isset($payload['enhanced_prompt']) ? (string) $payload['enhanced_prompt'] : null,
            $enhancedPrompts,
            isset($payload['applied_instruction']) ? (string) $payload['applied_instruction'] : null,
            $meta
        );
    }
}
