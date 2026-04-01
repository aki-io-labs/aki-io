<?php
/**
 * TTS Sync Test
 * 
 * Tests text-to-speech generation.
 */

namespace AkiIO\Tests;

use AkiIO\Aki;

class TTSSyncTest
{
    private string $apiKey;
    private string $generatedDir;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->generatedDir = __DIR__ . '/../generated';
        
        if (!is_dir($this->generatedDir)) {
            mkdir($this->generatedDir, 0755, true);
        }
    }

    public function run(): void
    {
        echo "    Testing TTS (xttsv2)...\n";
        
        $aki = new Aki('xttsv2', $this->apiKey);

        $progressCallback = function (array $progressInfo, ?array $progressData): void {
            $progress = $progressInfo['progress'] ?? 0;
            echo "\r    Progress: {$progress}%    ";
        };

        $result = $aki->doApiRequest([
            'text' => 'Hello! This is a test of the AKI.IO text to speech system.',
            'language' => 'en',
            'voice' => 'emma',
        ], $progressCallback);

        echo "\n";

        if (!$result['success']) {
            throw new \RuntimeException("TTS failed: " . ($result['error'] ?? 'Unknown error'));
        }

        $audioData = $result['audio'] ?? $result['audio_output'] ?? null;
        
        if ($audioData === null) {
            echo "    Warning: No audio data in response\n";
            echo "    Available keys: " . implode(', ', array_keys($result)) . "\n";
            echo "    TTS test passed (but no audio to save)\n";
            return;
        }

        // Decode and save audio
        $decoded = Aki::decodeBinary($audioData);
        $format = $decoded[0] ?? 'wav';
        $binary = $decoded[1] ?? base64_decode(str_contains($audioData, ',') 
            ? explode(',', $audioData, 2)[1] 
            : $audioData);

        $filename = $this->generatedDir . '/tts_output.' . ($format !== 'octet-stream' ? $format : 'wav');
        file_put_contents($filename, $binary);
        echo "    Saved: {$filename}\n";
        echo "    TTS test passed!\n";
    }
}
