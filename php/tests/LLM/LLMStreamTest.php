<?php
/**
 * LLM Stream Test
 * 
 * Tests LLM chat completion with progress streaming.
 */

namespace AkiIO\Tests;

use AkiIO\Aki;

class LLMStreamTest
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function run(): void
    {
        echo "    Testing LLM with streaming (llama3_8b_chat)...\n";
        
        $aki = new Aki('llama3_8b_chat', $this->apiKey);

        $chatContext = [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Count from 1 to 5, one number per line.'],
        ];

        $outputPosition = 0;
        $receivedProgress = false;

        $progressCallback = function (array $progressInfo, ?array $progressData) use (&$outputPosition, &$receivedProgress): void {
            $receivedProgress = true;
            $progress = $progressInfo['progress'] ?? 0;
            
            if ($progressData && isset($progressData['text'])) {
                $text = $progressData['text'];
                $newText = substr($text, $outputPosition);
                echo $newText;
                $outputPosition = strlen($text);
            }
        };

        $result = $aki->doApiRequest([
            'chat_context' => $chatContext,
            'temperature' => 0.3,
            'max_gen_tokens' => 100,
        ], $progressCallback);

        echo "\n";

        if (!$result['success']) {
            throw new \RuntimeException("LLM stream request failed: " . ($result['error'] ?? 'Unknown error'));
        }

        if (!$receivedProgress) {
            echo "    Warning: No progress callbacks received (response was too fast)\n";
        }

        $tokens = $result['num_generated_tokens'] ?? 0;
        echo "    Generated tokens: {$tokens}\n";
        echo "    LLM stream test passed!\n";
    }
}
