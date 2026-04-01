<?php
/**
 * AKI.IO LLM Simple Example
 * 
 * Simple LLM chat completion request.
 */

require_once __DIR__ . '/../src/Aki.php';
require_once __DIR__ . '/../src/functions.php';

use AkiIO\Aki;

$aki = new Aki('llama3_8b_chat', 'fc3a8c50-b12b-4d6a-ba07-c9f6a6c32c37');

$chatContext = [
    ['role' => 'system', 'content' => 'You are a helpful assistant named AKI.'],
    ['role' => 'assistant', 'content' => 'How can I help you today?'],
    ['role' => 'user', 'content' => 'Tell me a joke'],
];

$params = [
    'chat_context' => $chatContext,
    'top_k' => 40,
    'top_p' => 0.9,
    'temperature' => 0.8,
    'max_gen_tokens' => 1000,
];

$result = $aki->doApiRequest($params);

if ($result['success']) {
    echo "API JSON response:\n";
    print_r($result);
    echo "\nChat response:\n" . $result['text'] . "\n";
    echo "\nGenerated Tokens: " . $result['num_generated_tokens'] . "\n";
} else {
    echo "API Error: " . ($result['error_code'] ?? 'Unknown') . " - " . ($result['error'] ?? 'Unknown error') . "\n";
}
