<?php
/**
 * AKI.IO PHP Client - Edge Case Tests
 * 
 * Tests for edge cases like timeouts, errors, binary encoding, etc.
 */

namespace AkiIO\Tests;

use AkiIO\Aki;

class ErrorHandlingTest
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function run(): void
    {
        echo "    Testing error handling...\n";
        
        $aki = new Aki('invalid_endpoint', 'invalid_api_key');
        
        $result = $aki->doApiRequest([
            'prompt' => 'test'
        ]);
        
        if ($result['success']) {
            echo "    Warning: Request succeeded with invalid API key\n";
        } else {
            echo "    Error handling test passed!\n";
            echo "    Error: " . ($result['error'] ?? 'Unknown') . "\n";
            echo "    Error code: " . ($result['error_code'] ?? 'N/A') . "\n";
        }
    }
}
