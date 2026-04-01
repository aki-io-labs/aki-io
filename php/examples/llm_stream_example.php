<?php
/**
 * AKI.IO LLM Stream Example
 * 
 * LLM chat completion with streaming progress.
 */

require_once __DIR__ . '/../src/Aki.php';

use AkiIO\Aki;

$aki = new Aki('llama3_8b_chat', 'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37');

$chatContext = [
    ['role' => 'system', 'content' => 'You are a helpful assistant named AKI.'],
    ['role' => 'user', 'content' => 'Tell me a funny story with more than 100 words'],
];

$params = [
    'chat_context' => $chatContext,
    'top_k' => 40,
    'top_p' => 0.9,
    'temperature' => 0.8,
    'max_gen_tokens' => 1000,
];

$outputPosition = 0;

echo "\nAssistant: \n";

$progressCallback = function (array $progressInfo, ?array $progressData) use (&$outputPosition): void {
    if ($progressData && isset($progressData['text'])) {
        $text = $progressData['text'];
        echo substr($text, $outputPosition);
        $outputPosition = strlen($text);
    }
};

$result = $aki->doApiRequest($params, $progressCallback);

if ($result['success']) {
    echo substr($result['text'], $outputPosition) . "\n";
    echo "\nStats:\n";
    echo "  Generated Tokens: " . $result['num_generated_tokens'] . "\n";
    echo "  Compute Duration: " . number_format($result['compute_duration'] ?? 0, 2) . "s\n";
    echo "  Total Duration: " . number_format($result['total_duration'] ?? 0, 2) . "s\n";
    echo "\nChat completed!\n";
} else {
    echo "\nAPI Error: " . ($result['error_code'] ?? 'Unknown') . " - " . ($result['error'] ?? 'Unknown error') . "\n";
}
