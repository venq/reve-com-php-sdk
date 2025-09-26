<?php
declare(strict_types=1);

namespace Reve\SDK\Contracts;

interface SleeperInterface
{
    public function sleep(float $seconds): void;
}
