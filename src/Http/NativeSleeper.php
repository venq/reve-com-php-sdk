<?php
declare(strict_types=1);

namespace Reve\SDK\Http;

use Reve\SDK\Contracts\SleeperInterface;

final class NativeSleeper implements SleeperInterface
{
    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $microseconds = (int) round($seconds * 1_000_000);
        usleep($microseconds);
    }
}
