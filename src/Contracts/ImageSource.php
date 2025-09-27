<?php

declare(strict_types=1);

namespace Reve\SDK\Contracts;

use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

class ImageSource
{
    public const TYPE_URL = 'url';
    public const TYPE_DATA_URL = 'data-url';
    public const TYPE_FILEPATH = 'filepath';
    public const TYPE_STREAM = 'stream';

    /** @var resource|StreamInterface|string */
    protected $value;

    protected string $type;

    protected ?string $mime;

    protected ?string $filename;

    /**
     * @param resource|StreamInterface|string $value
     */
    protected function __construct(string $type, $value, ?string $mime = null, ?string $filename = null)
    {
        if (!in_array($type, [self::TYPE_URL, self::TYPE_DATA_URL, self::TYPE_FILEPATH, self::TYPE_STREAM], true)) {
            throw new InvalidArgumentException('Unsupported image source type: ' . $type);
        }

        $this->type = $type;
        $this->value = $value;
        $this->mime = $mime;
        $this->filename = $filename;
    }

    public static function fromUrl(string $url, ?string $mime = null): self
    {
        if ($url === '') {
            throw new InvalidArgumentException('URL cannot be empty.');
        }

        return new self(self::TYPE_URL, $url, $mime);
    }

    public static function fromDataUrl(string $dataUrl, ?string $mime = null): self
    {
        if (!str_starts_with($dataUrl, 'data:')) {
            throw new InvalidArgumentException('Data URL must start with data:');
        }

        return new self(self::TYPE_DATA_URL, $dataUrl, $mime);
    }

    public static function fromFile(string $path, ?string $mime = null, ?string $filename = null): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('File path must point to a readable file.');
        }

        return new self(self::TYPE_FILEPATH, $path, $mime, $filename ?? basename($path));
    }

    /**
     * @param resource|StreamInterface|string $stream
     */
    public static function fromStream($stream, ?string $mime = null, ?string $filename = null): self
    {
        if ($stream instanceof StreamInterface) {
            return new self(self::TYPE_STREAM, $stream, $mime, $filename);
        }

        if (is_resource($stream)) {
            return new self(self::TYPE_STREAM, Utils::streamFor($stream), $mime, $filename);
        }

        if (is_string($stream)) {
            return new self(self::TYPE_STREAM, Utils::streamFor($stream), $mime, $filename);
        }

        throw new InvalidArgumentException('Stream source must be a resource, string buffer, or StreamInterface.');
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return resource|StreamInterface|string
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getMime(): ?string
    {
        return $this->mime;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function isMultipart(): bool
    {
        return in_array($this->type, [self::TYPE_FILEPATH, self::TYPE_STREAM], true);
    }

    public function appliesAsJson(): bool
    {
        return !$this->isMultipart();
    }

    /**
     * @return array{
     *     name: string,
     *     contents: resource|StreamInterface|string,
     *     filename?: string,
     *     headers?: array<string, string>
     * }
     */
    public function toMultipartEntry(string $fieldName): array
    {
        if (!$this->isMultipart()) {
            throw new InvalidArgumentException('Only stream/file sources can be used as multipart.');
        }

        $contents = $this->type === self::TYPE_FILEPATH
            ? Utils::tryFopen($this->value, 'rb')
            : $this->value;

        $entry = [
            'name' => $fieldName,
            'contents' => $contents,
        ];

        if ($this->filename) {
            $entry['filename'] = $this->filename;
        }

        if ($this->mime) {
            $entry['headers'] = ['Content-Type' => $this->mime];
        }

        return $entry;
    }

    /**
     * @return array<string, string>
     */
    public function toJsonField(string $prefix): array
    {
        if ($this->isMultipart()) {
            throw new InvalidArgumentException('Multipart sources cannot be inlined into JSON payload.');
        }

        $key = $prefix . ($this->type === self::TYPE_URL ? '_url' : '_data_url');
        return [$key => $this->value];
    }
}
