<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Reve\SDK\Contracts\CreateImageRequest;
use Reve\SDK\Contracts\EditImageRequest;
use Reve\SDK\Contracts\ImageSource;
use Reve\SDK\Contracts\RemixRequest;
use Reve\SDK\ReveClient;

$client = ReveClient::createDefault();

// 1. Start a generation task.
$generation = $client->createImage(new CreateImageRequest(
    prompt: 'abstract art installation made of glass, studio lighting',
    width: 768,
    height: 768,
));

echo 'Generation task id: ' . $generation->taskId . PHP_EOL;

$completed = $client->getPolling()->waitUntilCompleted($generation->taskId);
echo 'First image URL: ' . $completed->imageUrls[0] . PHP_EOL;

// 2. Remix using the finished image.
$remix = $client->remix(new RemixRequest(
    image: ImageSource::fromUrl($completed->imageUrls[0]),
    prompt: 'winter sunrise palette, cinematic backlight',
    variation: 0.3,
));

echo 'Remix task id: ' . $remix->taskId . PHP_EOL;
