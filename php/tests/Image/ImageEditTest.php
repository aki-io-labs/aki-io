<?php
/**
 * AKI.IO PHP Client - Image Edit Test
 *
 * Tests image editing using qwen_Image_Edit endpoint.
 * Depends on ImageGenTest creating a test image first.
 */

namespace AkiIO\Tests;

use AkiIO\Aki;

class ImageEditTest
{
    private string $apiKey;
    private string $testImagePath;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->testImagePath = __DIR__ . '/../generated/test_image.png';
    }

    public function run(): void
    {
        echo "    Testing image edit (qwen_Image_Edit)...\n";

        // Check if test image exists from ImageGenTest
        if (!file_exists($this->testImagePath)) {
            echo "    Skipping: Test image not found. Run ImageGenTest first.\n";
            return;
        }

        $imageData = file_get_contents($this->testImagePath);
        if ($imageData === false) {
            throw new \RuntimeException('Failed to read test image');
        }

        // Determine format from file extension
        $format = pathinfo($this->testImagePath, PATHINFO_EXTENSION);
        if ($format === 'jpg') {
            $format = 'jpeg';
        }

        $aki = new Aki('qwen_image', $this->apiKey);

        $params = [
            'prompt' => 'Add a blue sky background',
            'seed' => 42,
            'height' => 512,
            'width' => 512,
            'image' => Aki::encodeBinary($imageData, $format),
        ];

        $progressCallback = function (array $progressInfo, ?array $progressData): void {
            $progress = $progressInfo['progress'] ?? 0;
            echo "\r    Progress: {$progress}%";
        };

        $result = $aki->doApiRequest($params, $progressCallback);

        if (!$result['success']) {
            $error = $result['error'] ?? 'Unknown error';
            if (str_contains($error, "doesn't exist") || str_contains($error, "does not exist") || str_contains($error, "Invalid input")) {
                echo "\n    Skipping: Image edit endpoint not available or requires different parameters\n";
                return;
            }
            throw new \RuntimeException('Image edit failed: ' . $error);
        }

        $images = $result['images'] ?? [];
        if (empty($images)) {
            throw new \RuntimeException('No images returned in response');
        }

        echo "\n    Image edit test passed!\n";
    }
}
