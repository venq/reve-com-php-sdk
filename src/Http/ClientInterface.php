<?php
declare(strict_types=1);

namespace Reve\SDK\Http;

use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Reve\SDK\Config\ClientConfig;

interface ClientInterface extends PsrClientInterface
{
    public function getConfig(): ClientConfig;
}
