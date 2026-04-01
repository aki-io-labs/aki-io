<?php
/**
 * LLM Simple Test
 * 
 * Tests basic LLM chat completion without streaming.
 */

namespace AkiIO\Tests;

use AkiIO\Aki;

class LLMSimpleTest
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function run(): void
    {
        echo "    Testing LLM simple request (llama3_8b_chat)...\n";
        
        $aki = new Aki('llama3_8b_chat', $this->apiKey);

        $chatContext = [
            ['role' => 'system', 'content' => 'You are a helpful assistant named AKI.'],
            ['role' => 'user', 'content' => 'Tell me a short joke about programming.'],
        ];

        $result = $aki->doApiRequest([
            'chat_context' => $chatContext,
            'top_k' => 40,
            'top_p' => 0.9,
            'temperature' => 0.8,
            'max_gen_tokens' => 200,
        ]);

        if (!$result['success']) {
            throw new \RuntimeException("LLM request failed: " . ($result['error'] ?? 'Unknown error'));
        }

        $text = $result['text'] ?? '';
        $tokens = $result['num_generated_tokens'] ?? 0;

        if (strlen($text) < 10) {
            throw new \RuntimeException("Response too short, got: {$text}");
        }

        echo "    Response: " . substr($text, 0, 100) . "...\n";
        echo "    Generated tokens: {$tokens}\n";
        echo "    LLM simple test passed!\n";
    }
}
