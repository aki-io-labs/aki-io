<?php
/**
 * AKI.IO Image Generation Example
 * 
 * Generate images using the z_image_turbo endpoint.
 */

require_once __DIR__ . '/../src/Aki.php';

use AkiIO\Aki;

$aki = new Aki('z_image_turbo', 'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37');

$params = [
    'prompt' => 'Astronaut on Mars holding a banner which states "AKI is happy to serve your model" during sunset sitting on a giant yellow rubber duck',
    'seed' => -1,
    'height' => 512,
    'width' => 512,
];

$progressCallback = function (array $progressInfo, ?array $progressData): void {
    $progress = $progressInfo['progress'] ?? 0;
    echo "\rProgress: {$progress}%    ";
};

$result = $aki->doApiRequest($params, $progressCallback);

if ($result['success']) {
    $images = $result['images'] ?? [];
    
    foreach ($images as $idx => $imageData) {
        $decoded = Aki::decodeBinary($imageData);
        $format = $decoded[0] ?? 'png';
        $binary = $decoded[1] ?? base64_decode(str_contains($imageData, ',') 
            ? explode(',', $imageData, 2)[1] 
            : $imageData);
        
        $filename = "image_{$idx}." . ($format !== 'octet-stream' ? $format : 'png');
        file_put_contents($filename, $binary);
        echo "\nOutput image saved at {$filename}\n";
    }
} else {
    echo "\nAPI Error: " . ($result['error_code'] ?? 'Unknown') . " - " . ($result['error'] ?? 'Unknown error') . "\n";
}
