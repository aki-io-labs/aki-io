<?php
/**
 * AKI.IO LLM Async Example
 *
 * Demonstrates async/await pattern for non-blocking API requests.
 * Requires symfony/http-client: composer require symfony/http-client
 */

require_once __DIR__ . '/../src/Aki.php';
require_once __DIR__ . '/../src/functions.php';

use AkiIO\Aki;

// Get API key from environment or use default
$apiKey = getenv('AKI_API_KEY') ?: 'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37';

$aki = new Aki('llama3_8b_chat', $apiKey);

$chatContext = [
    ['role' => 'system', 'content' => 'You are a helpful assistant named AKI.'],
    ['role' => 'user', 'content' => 'Tell me about PHP async programming in 2 sentences'],
];

$params = [
    'chat_context' => $chatContext,
    'top_k' => 40,
    'top_p' => 0.9,
    'temperature' => 0.8,
    'max_gen_tokens' => 200,
];

echo "=== Async/Await Example ===\n\n";

// Example 1: Using doApiRequestAwait (synchronous wrapper)
echo "1. Using doApiRequestAwait (blocking):\n";
echo "----------------------------------------\n";

$outputPosition = 0;
$startTime = microtime(true);

$progressCallback = function (array $progressInfo, ?array $progressData) use (&$outputPosition): void {
    if (isset($progressInfo['progress'])) {
        printf("  Progress: %d%%", $progressInfo['progress']);
        if (isset($progressInfo['queue_position']) && $progressInfo['queue_position'] > 0) {
            printf(" (Queue: %d)", $progressInfo['queue_position']);
        }
        echo "\n";
    }
    if ($progressData && isset($progressData['text'])) {
        $text = $progressData['text'];
        echo substr($text, $outputPosition);
        $outputPosition = strlen($text);
    }
};

$result = $aki->doApiRequestAwait($params, $progressCallback);

$duration = microtime(true) - $startTime;

if ($result['success']) {
    echo substr($result['text'], $outputPosition) . "\n";
    echo "----------------------------------------\n";
    echo "Generated Tokens: " . ($result['num_generated_tokens'] ?? 'N/A') . "\n";
    echo "Duration: " . number_format($duration, 2) . "s\n";
} else {
    echo "API Error: " . ($result['error_code'] ?? 'Unknown') . " - " . ($result['error'] ?? 'Unknown error') . "\n";
}

// Example 2: Using doApiRequestAsync (generator-based, for more control)
echo "\n2. Using doApiRequestAsync (generator):\n";
echo "----------------------------------------\n";

$aki2 = new Aki('llama3_8b_chat', $apiKey);
$startTime = microtime(true);

$generator = $aki2->doApiRequestAsync($params, $progressCallback);

// Iterate over the generator to process progress updates
foreach ($generator as $progress) {
    // Progress updates are yielded here
    // The actual handling is done via progressCallback
    echo "  [Generator yielded progress update]\n";
}

$result2 = $generator->getReturn();
$duration = microtime(true) - $startTime;

if ($result2['success']) {
    echo "\nResponse: " . $result2['text'] . "\n";
    echo "----------------------------------------\n";
    echo "Generated Tokens: " . ($result2['num_generated_tokens'] ?? 'N/A') . "\n";
    echo "Duration: " . number_format($duration, 2) . "s\n";
} else {
    echo "API Error: " . ($result2['error_code'] ?? 'Unknown') . " - " . ($result2['error'] ?? 'Unknown error') . "\n";
}

echo "\n=== Example Complete ===\n";
