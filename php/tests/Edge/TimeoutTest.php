<?php
/**
 * AKI.IO PHP Client - Edge Case Tests
 * 
 * Tests for edge cases like timeouts, errors, binary encoding, etc.
 */

namespace AkiIO\Tests;

use AkiIO\Aki;

class TimeoutTest
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function run(): void
    {
        echo "    Testing timeout handling...\n";
        
        $aki = new Aki('llama3_8b_chat', $this->apiKey, [
            'api_server' => 'https://httpbin.org'
        ]);

        $result = $aki->doApiRequest([
            'chat_context' => [['role' => 'user', 'content' => 'test']],
        ]);

        if (!$result['success']) {
            echo "    Timeout test passed (request failed as expected)!\n";
        } else {
            echo "    Warning: Request succeeded unexpectedly\n";
        }
    }
}
