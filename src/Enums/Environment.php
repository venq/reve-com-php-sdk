<?php
declare(strict_types=1);

namespace Reve\SDK\Enums;

enum Environment: string
{
    case Official = 'official';
    case Preview = 'preview';

    public function isPreview(): bool
    {
        return $this === self::Preview;
    }
}
