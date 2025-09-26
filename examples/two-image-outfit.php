<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Reve\SDK\Contracts\EditImageRequest;
use Reve\SDK\Contracts\ImageSource;
use Reve\SDK\Contracts\MaskSource;
use Reve\SDK\ReveClient;

// Expected assets:
// - assets/model-base.png : base photo of the person
// - assets/model-mask.png : mask marking the outfit region
// - assets/garment-design.png : concept art or outfit reference

$client = ReveClient::createDefault();

$baseImage = ImageSource::fromFile(__DIR__ . '/assets/model-base.png', 'image/png');
$garmentConceptPath = __DIR__ . '/assets/garment-design.png';
$mask = MaskSource::fromFile(__DIR__ . '/assets/model-mask.png', 'image/png');

$garmentData = file_get_contents($garmentConceptPath);
if ($garmentData === false) {
    throw new RuntimeException('Failed to read garment concept image.');
}

$garmentDataUrl = 'data:image/png;base64,' . base64_encode($garmentData);

$request = new EditImageRequest(
    image: $baseImage,
    instruction: 'Replace the outfit on the masked region using the garment from the reference data URL. Keep the model pose and lighting consistent.',
    mask: $mask,
    strength: 0.65,
    preserve: [
        'face' => true,
        'hands' => true,
    ],
    metadata: [
        'reference_outfit_data_url' => $garmentDataUrl,
    ]
);

$task = $client->editImage($request);

echo 'Edit task: ' . $task->taskId . PHP_EOL;

$finished = $client->getPolling()->waitUntilCompleted($task->taskId);

echo 'Result image URL: ' . ($finished->imageUrls[0] ?? '(missing)') . PHP_EOL;
