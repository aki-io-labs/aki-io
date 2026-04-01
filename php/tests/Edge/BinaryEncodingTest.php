<?php
/**
 * AKI.IO PHP Client - Edge Case Tests
 * 
 * Tests for edge cases like timeouts, errors, binary encoding, etc.
 */

namespace AkiIO\Tests;

use AkiIO\Aki;

class BinaryEncodingTest
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function run(): void
    {
        echo "    Testing binary encoding/decoding...\n";
        
        // Test encodeBinary
        $testData = "Hello World";
        $encoded = Aki::encodeBinary($testData, 'png');
        
        if (!str_contains($encoded, 'data:image/png;base64,')) {
            throw new \RuntimeException("encodeBinary failed: missing MIME header");
        }
        
        $decoded = base64_decode(explode(',', $encoded, 2)[1]);
        if ($decoded !== $testData) {
            throw new \RuntimeException("encodeBinary failed: data mismatch");
        }
        
        // Test decodeBinary
        list($format, $binary) = Aki::decodeBinary($encoded);
        
        if ($format !== 'png') {
            throw new \RuntimeException("decodeBinary failed: format mismatch");
        }
        
        if ($binary !== $testData) {
            throw new \RuntimeException("decodeBinary failed: binary mismatch");
        }
        
        // Test checkIfValidBase64String
        if (!Aki::checkIfValidBase64String($encoded)) {
            throw new \RuntimeException("checkIfValidBase64String failed: should return true");
        }
        
        // Test with invalid base64
        if (Aki::checkIfValidBase64String("not-valid-base64!!!")) {
            throw new \RuntimeException("checkIfValidBase64String failed: should return false for invalid");
        }
        
        echo "    Binary encoding test passed!\n";
    }
}
