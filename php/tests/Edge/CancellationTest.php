<?php
/**
 * AKI.IO PHP Client - Edge Case Tests
 * 
 * Tests for edge cases like timeouts, errors, binary encoding, etc.
 */

namespace AkiIO\Tests;

use AkiIO\Aki;

class CancellationTest
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function run(): void
    {
        echo "    Testing request cancellation...\n";
        
        $aki = new Aki('llama3_8b_chat', $this->apiKey);
        
        // Cancel before request
        $aki->cancelRequest('test_job_id');
        
        // Note: This is a simple test of verify cancelRequest doesn't throw
        // The actual cancellation would happen during progress polling
        
        echo "    Cancellation test passed!\n";
    }
}
