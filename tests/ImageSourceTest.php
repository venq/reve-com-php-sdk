<?php

declare(strict_types=1);

namespace Reve\SDK\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Reve\SDK\Contracts\ImageSource;

final class ImageSourceTest extends TestCase
{
    public function testUrlJsonField(): void
    {
        $source = ImageSource::fromUrl('https://example.test/image.png', 'image/png');
        $field = $source->toJsonField('image');
        self::assertSame(['image_url' => 'https://example.test/image.png'], $field);
        self::assertFalse($source->isMultipart());
    }

    public function testStreamMultipart(): void
    {
        $source = ImageSource::fromStream('binary-data', 'image/png', 'blob.png');
        self::assertTrue($source->isMultipart());
        $entry = $source->toMultipartEntry('image');
        self::assertSame('image', $entry['name']);
        self::assertSame('blob.png', $entry['filename']);
        self::assertSame(['Content-Type' => 'image/png'], $entry['headers']);
    }

    public function testFileMustExist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ImageSource::fromFile('missing.png');
    }
}
