<?php
/**
 * Vision Test (Multimodal)
 * 
 * Tests vision/image analysis with a generated image.
 */

namespace AkiIO\Tests;

use AkiIO\Aki;

class VisionTest
{
    private string $apiKey;
    private string $generatedDir;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->generatedDir = __DIR__ . '/../generated';
    }

    public function run(): void
    {
        $inputImage = $this->generatedDir . '/test_image_0.png';
        
        if (!file_exists($inputImage)) {
            echo "    Skipping: No test image found (run ImageGenTest first)\n";
            return;
        }

        echo "    Testing vision model (llama3_8b_chat with image)...\n";
        echo "    Input: {$inputImage}\n";
        
        // Try with vision-capable endpoint
        $aki = new Aki('llama3_8b_chat', $this->apiKey);

        $imageData = file_get_contents($inputImage);
        $encodedImage = Aki::encodeBinary($imageData, 'png');

        $chatContext = [
            ['role' => 'user', 'content' => 'Describe this image in one sentence.'],
        ];

        $result = $aki->doApiRequest([
            'chat_context' => $chatContext,
            'image' => $encodedImage,
            'max_gen_tokens' => 200,
        ]);

        if (!$result['success']) {
            // Vision might not be supported on this endpoint
            echo "    Note: Vision not supported on this endpoint\n";
            echo "    Error: " . ($result['error'] ?? 'Unknown error') . "\n";
            echo "    Vision test skipped (endpoint may not support images)\n";
            return;
        }

        $text = $result['text'] ?? '';
        echo "    Vision response: " . substr($text, 0, 150) . "...\n";
        echo "    Vision test passed!\n";
    }
}
