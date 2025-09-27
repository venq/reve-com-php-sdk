<?php

declare(strict_types=1);

namespace Reve\SDK\Contracts;

class MaskSource extends ImageSource
{
    public static function fromUrl(string $url, ?string $mime = null): self
    {
        return new self(self::TYPE_URL, $url, $mime);
    }

    public static function fromDataUrl(string $dataUrl, ?string $mime = null): self
    {
        return new self(self::TYPE_DATA_URL, $dataUrl, $mime);
    }

    public static function fromFile(string $path, ?string $mime = null, ?string $filename = null): self
    {
        return new self(self::TYPE_FILEPATH, $path, $mime, $filename ?? basename($path));
    }

    public static function fromStream($stream, ?string $mime = null, ?string $filename = null): self
    {
        $source = parent::fromStream($stream, $mime, $filename);
        return new self($source->type, $source->value, $source->mime, $source->filename);
    }
}
