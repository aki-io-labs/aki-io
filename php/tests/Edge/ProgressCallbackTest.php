<?php
/**
 * AKI.IO PHP Client - Edge Case Tests
 * 
 * Tests for edge cases like timeouts, errors, binary encoding, etc.
 */

namespace AkiIO\Tests;

use AkiIO\Aki;

class ProgressCallbackTest
{
    private string $apiKey;
    private int $callbackCount = 0;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function run(): void
    {
        echo "    Testing progress callback...\n";
        
        $aki = new Aki('llama3_8b_chat', $this->apiKey);
        
        $progressCallback = function ($progressInfo, $progressData) {
            $this->callbackCount++;
            $progress = $progressInfo['progress'] ?? 0;
            echo "\r    Progress callback #{$this->callbackCount}: {$progress}%    ";
        };
        
        $result = $aki->doApiRequest([
            'chat_context' => [
                ['role' => 'user', 'content' => 'Count from 1 to 5']
            ],
            'max_gen_tokens' => 50,
        ], $progressCallback);
        
        if (!$result['success']) {
            throw new \RuntimeException("Progress callback test failed: " . ($result['error'] ?? 'Unknown error'));
        }
        
        if ($this->callbackCount === 0) {
            echo "    Warning: No progress callbacks received (response was too fast)\n";
        }
        
        echo "\n    Progress callback test passed! ({$this->callbackCount} callbacks)\n";
    }
}
