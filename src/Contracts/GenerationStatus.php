<?php
declare(strict_types=1);

namespace Reve\SDK\Contracts;

use InvalidArgumentException;
use Reve\SDK\Enums\TaskStatus;

final class GenerationStatus
{
    /**
     * @param string[]|null $warnings
     */
    public function __construct(
        public readonly TaskStatus $status,
        public readonly ?int $estimatedWaitSeconds,
        public readonly ?int $credits,
        public readonly ?array $warnings,
        public readonly ?CompletedGeneration $result
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $statusValue = (string) ($payload['status'] ?? '');
        $status = TaskStatus::from($statusValue);

        $result = null;
        if (isset($payload['result']) && is_array($payload['result'])) {
            $result = CompletedGeneration::fromArray($payload['result']);
        }

        $warnings = null;
        if (isset($payload['warnings']) && is_array($payload['warnings'])) {
            $warnings = array_map('strval', $payload['warnings']);
        }

        return new self(
            $status,
            isset($payload['estimated_wait_seconds']) ? (int) $payload['estimated_wait_seconds'] : null,
            isset($payload['credits']) ? (int) $payload['credits'] : null,
            $warnings,
            $result
        );
    }

    public function ensureCompleted(): CompletedGeneration
    {
        if ($this->status !== TaskStatus::Completed || $this->result === null) {
            throw new InvalidArgumentException('Task is not completed.');
        }

        return $this->result;
    }
}
