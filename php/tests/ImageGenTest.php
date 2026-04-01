<?php
/**
 * AKI.IO PHP Client - Image Generation Test
 *
 * Tests image generation using z_image_turbo endpoint.
 * Generates test images for use in multimodal tests.
 */

namespace AkiIO\Tests;

use AkiIO\Aki;

class ImageGenTest
{
    private string $apiKey;
    private string $testImagePath;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->testImagePath = __DIR__ . '/generated/test_image.png';
    }

    public function run(): void
    {
        echo "    Testing image generation (z_Image_turbo)...\n";

        // Ensure generated directory exists
        $dir = dirname($this->testImagePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $aki = new Aki('z_image_turbo', $this->apiKey);

        $params = [
            'prompt' => 'A simple red circle on white background, minimal style',
            'seed' => 42,
            'height' => 512,
            'width' => 512,
        ];

        $progressCallback = function (array $progressInfo, ?array $progressData): void {
            $progress = $progressInfo['progress'] ?? 0;
            echo "\r    Progress: {$progress}%";
        };

        $result = $aki->doApiRequest($params, $progressCallback);

        if (!$result['success']) {
            $error = $result['error'] ?? 'Unknown error';
            if (str_contains($error, "doesn't exist") || str_contains($error, "does not exist")) {
                echo "\n    Skipping: Image generation endpoint not available\n";
                return;
            }
            throw new \RuntimeException('Image generation failed: ' . $error);
        }

        $images = $result['images'] ?? [];
        if (empty($images)) {
            throw new \RuntimeException('No images returned in response');
        }

        // Save first image for multimodal tests
        $imageData = $images[0];
        $decoded = Aki::decodeBinary($imageData);
        $format = $decoded[0] ?? 'png';
        $binary = $decoded[1] ?? base64_decode(
            str_contains($imageData, ',')
                ? explode(',', $imageData, 2)[1]
                : $imageData
        );

        file_put_contents($this->testImagePath, $binary);
        echo "\n    Test image saved at {$this->testImagePath}\n";

        if (!file_exists($this->testImagePath)) {
            throw new \RuntimeException('Failed to save test image');
        }

        echo "    Image generation test passed!\n";
    }

    public function getTestImagePath(): string
    {
        return $this->testImagePath;
    }
}
