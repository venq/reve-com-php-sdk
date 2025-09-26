<?php
declare(strict_types=1);

namespace Reve\SDK\Contracts;

use InvalidArgumentException;

class CreateImageResponse
{
    public function __construct(
        public readonly string $taskId,
        /** @var string[]|null */
        public readonly ?array $warnings = null
    ) {
        if ($taskId === '') {
            throw new InvalidArgumentException('Task ID cannot be empty.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $taskId = (string) ($payload['task_id'] ?? '');
        $warnings = null;
        if (isset($payload['warnings']) && is_array($payload['warnings'])) {
            $warnings = array_map('strval', $payload['warnings']);
        }

        return new self($taskId, $warnings);
    }
}
