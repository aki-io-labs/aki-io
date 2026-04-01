<?php
/**
 * AKI.IO TTS Example
 * 
 * Text-to-speech generation example.
 */

require_once __DIR__ . '/../src/Aki.php';

use AkiIO\Aki;

$aki = new Aki('xttsv2', 'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37');

$params = [
    'text' => 'Hello! This is a test of the text to speech system.',
    'language' => 'en',
    'voice' => 'emma',
];

$progressCallback = function (array $progressInfo, ?array $progressData): void {
    $progress = $progressInfo['progress'] ?? 0;
    echo "\rProgress: {$progress}%    ";
};

$result = $aki->doApiRequest($params, $progressCallback);

if ($result['success']) {
    $audioData = $result['audio'] ?? $result['audio_output'] ?? null;
    
    if ($audioData) {
        $decoded = Aki::decodeBinary($audioData);
        $format = $decoded[0] ?? 'wav';
        $binary = $decoded[1] ?? base64_decode(str_contains($audioData, ',') 
            ? explode(',', $audioData, 2)[1] 
            : $audioData);
        
        $filename = 'output.' . ($format !== 'octet-stream' ? $format : 'wav');
        file_put_contents($filename, $binary);
        echo "\nAudio saved to: {$filename}\n";
    } else {
        echo "\nNo audio data received.\n";
    }
} else {
    echo "\nAPI Error: " . ($result['error_code'] ?? 'Unknown') . " - " . ($result['error'] ?? 'Unknown error') . "\n";
}
