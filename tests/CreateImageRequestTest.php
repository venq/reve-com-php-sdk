<?php

declare(strict_types=1);

namespace Reve\SDK\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Reve\SDK\Contracts\CreateImageRequest;

final class CreateImageRequestTest extends TestCase
{
    public function testPayloadIsNormalised(): void
    {
        $request = new CreateImageRequest(
            prompt: '  Minimalist chair  ',
            negativePrompt: 'clutter',
            width: 512,
            height: 512,
            batchSize: 2,
            seed: 42,
            model: 'model-v1',
            enhancePrompt: false,
            metadata: ['source' => 'phpunit']
        );

        $payload = $request->toPayload();

        self::assertSame('Minimalist chair', $payload['prompt']);
        self::assertSame('clutter', $payload['negative_prompt']);
        self::assertSame(512, $payload['width']);
        self::assertSame(512, $payload['height']);
        self::assertSame(2, $payload['batch_size']);
        self::assertSame(42, $payload['seed']);
        self::assertSame('model-v1', $payload['model']);
        self::assertFalse($payload['enhance_prompt']);
        self::assertSame(['source' => 'phpunit'], $payload['metadata']);
    }

    public function testThrowsOnInvalidDimension(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CreateImageRequest(prompt: 'demo', width: 500, height: 512);
    }
}
