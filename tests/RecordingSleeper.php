<?php

declare(strict_types=1);

namespace Reve\SDK\Tests;

use Reve\SDK\Contracts\SleeperInterface;

/** @internal */
final class RecordingSleeper implements SleeperInterface
{
    /** @var list<float> */
    public array $calls = [];

    public function sleep(float $seconds): void
    {
        $this->calls[] = $seconds;
    }
}
