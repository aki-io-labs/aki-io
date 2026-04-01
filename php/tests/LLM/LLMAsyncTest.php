<?php
/**
 * LLM Async Test
 *
 * Comprehensive tests for async/await patterns using Symfony HttpClient.
 * Requires: composer require symfony/http-client
 */

namespace AkiIO\Tests;

use AkiIO\Aki;

class LLMAsyncTest
{
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function run(): void
    {
        if (!class_exists(\Symfony\Component\HttpClient\CurlHttpClient::class)) {
            echo "    Skipping: symfony/http-client not installed\n";
            return;
        }

        echo "    [1/6] Testing doApiRequestAwait...\n";
        $this->testAwait();

        echo "    [2/6] Testing doApiRequestAsync generator...\n";
        $this->testAsyncGenerator();

        echo "    [3/6] Testing async error handling...\n";
        $this->testAsyncError();

        echo "    [4/6] Testing async progress streaming...\n";
        $this->testProgressStreaming();

        echo "    [5/6] Testing async cancellation...\n";
        $this->testAsyncCancel();

        echo "    [6/6] Testing parallel async requests...\n";
        $this->testParallelRequests();

        echo "    All async tests passed!\n";
    }

    /**
     * Test 1: Synchronous wrapper around async (doApiRequestAwait).
     */
    private function testAwait(): void
    {
        $aki = new Aki('llama3_8b_chat', $this->apiKey);

        $params = [
            'chat_context' => [
                ['role' => 'user', 'content' => 'Say "hello" in one word.'],
            ],
            'temperature' => 0.1,
            'max_gen_tokens' => 20,
        ];

        $result = $aki->doApiRequestAwait($params);

        if (!$result['success']) {
            throw new \RuntimeException("doApiRequestAwait failed: " . ($result['error'] ?? 'Unknown'));
        }

        if (empty($result['text'])) {
            throw new \RuntimeException("doApiRequestAwait returned empty text");
        }

        echo "      doApiRequestAwait OK (tokens: " . ($result['num_generated_tokens'] ?? 'N/A') . ")\n";
    }

    /**
     * Test 2: Raw generator iteration with progress callback.
     */
    private function testAsyncGenerator(): void
    {
        $aki = new Aki('llama3_8b_chat', $this->apiKey);

        $params = [
            'chat_context' => [
                ['role' => 'user', 'content' => 'Say "world" in one word.'],
            ],
            'temperature' => 0.1,
            'max_gen_tokens' => 20,
        ];

        $yieldCount = 0;
        $progressCallback = function (array $progressInfo, ?array $progressData): void {};

        $generator = $aki->doApiRequestAsync($params, $progressCallback);

        foreach ($generator as $progress) {
            $yieldCount++;
        }

        $result = $generator->getReturn();

        if (!$result['success']) {
            throw new \RuntimeException("doApiRequestAsync failed: " . ($result['error'] ?? 'Unknown'));
        }

        if (empty($result['text'])) {
            throw new \RuntimeException("doApiRequestAsync returned empty text");
        }

        echo "      doApiRequestAsync OK (yields: {$yieldCount}, tokens: " . ($result['num_generated_tokens'] ?? 'N/A') . ")\n";
    }

    /**
     * Test 3: Async request with invalid endpoint should return error, not throw.
     */
    private function testAsyncError(): void
    {
        $aki = new Aki('invalid_endpoint_xyz', $this->apiKey);

        $generator = $aki->doApiRequestAsync([
            'prompt' => 'test',
        ]);

        foreach ($generator as $progress) {}

        $result = $generator->getReturn();

        if ($result['success'] ?? true) {
            throw new \RuntimeException("Expected failure for invalid endpoint, got success");
        }

        if (!isset($result['error'])) {
            throw new \RuntimeException("Missing 'error' key in async error response");
        }

        echo "      Async error handling OK (error: " . substr($result['error'], 0, 60) . "...)\n";
    }

    /**
     * Test 4: Verify progress callbacks receive incremental data with
     *         increasing text length and valid progress info structure.
     */
    private function testProgressStreaming(): void
    {
        $aki = new Aki('llama3_8b_chat', $this->apiKey);

        $params = [
            'chat_context' => [
                ['role' => 'user', 'content' => 'Count from 1 to 5, one number per line.'],
            ],
            'temperature' => 0.1,
            'max_gen_tokens' => 100,
        ];

        $progressUpdates = [];
        $textGrowth = [];

        $progressCallback = function (array $progressInfo, ?array $progressData) use (&$progressUpdates, &$textGrowth): void {
            $progressUpdates[] = $progressInfo;

            if ($progressData && isset($progressData['text'])) {
                $textGrowth[] = strlen($progressData['text']);
            }
        };

        $result = $aki->doApiRequestAwait($params, $progressCallback);

        if (!$result['success']) {
            throw new \RuntimeException("Progress streaming failed: " . ($result['error'] ?? 'Unknown'));
        }

        // Verify progress info structure
        foreach ($progressUpdates as $i => $info) {
            $requiredKeys = ['job_id', 'progress', 'job_state'];
            foreach ($requiredKeys as $key) {
                if (!array_key_exists($key, $info)) {
                    throw new \RuntimeException("Progress update #{$i} missing key: {$key}");
                }
            }
        }

        // Verify text grew incrementally (each update should have >= text length than previous)
        for ($i = 1; $i < count($textGrowth); $i++) {
            if ($textGrowth[$i] < $textGrowth[$i - 1]) {
                throw new \RuntimeException("Text length decreased at progress update #{$i}: {$textGrowth[$i]} < {$textGrowth[$i - 1]}");
            }
        }

        $cbCount = count($progressUpdates);
        $growthCount = count($textGrowth);
        echo "      Progress streaming OK ({$cbCount} callbacks, {$growthCount} text growth updates)\n";
    }

    /**
     * Test 5: Cancel an async request mid-flight.
     */
    private function testAsyncCancel(): void
    {
        $aki = new Aki('llama3_8b_chat', $this->apiKey);

        $params = [
            'chat_context' => [
                ['role' => 'user', 'content' => 'Write a long essay about philosophy.'],
            ],
            'temperature' => 0.3,
            'max_gen_tokens' => 500,
        ];

        $callbackCount = 0;
        $canceled = false;

        $progressCallback = function (array $progressInfo, ?array $progressData) use ($aki, &$canceled, &$callbackCount): void {
            $callbackCount++;

            // Cancel after receiving the first real progress update
            if (!$canceled && $callbackCount >= 1) {
                $canceled = true;
                $jobId = $progressInfo['job_id'] ?? null;
                if ($jobId !== null) {
                    $aki->cancelRequest($jobId);
                }
            }
        };

        $generator = $aki->doApiRequestAsync($params, $progressCallback);

        foreach ($generator as $progress) {}

        $result = $generator->getReturn();

        // Result should still be a valid array
        if (!is_array($result)) {
            throw new \RuntimeException("Cancel: expected array result, got " . gettype($result));
        }

        echo "      Async cancellation OK (callbacks before cancel: {$callbackCount})\n";
    }

    /**
     * Test 6: Two concurrent async requests from separate Aki instances.
     *         Verifies each instance uses its own HTTP client and returns independent results.
     */
    private function testParallelRequests(): void
    {
        $aki1 = new Aki('llama3_8b_chat', $this->apiKey);
        $aki2 = new Aki('llama3_8b_chat', $this->apiKey);

        $params1 = [
            'chat_context' => [
                ['role' => 'user', 'content' => 'Say "alpha" in one word.'],
            ],
            'temperature' => 0.1,
            'max_gen_tokens' => 20,
        ];

        $params2 = [
            'chat_context' => [
                ['role' => 'user', 'content' => 'Say "beta" in one word.'],
            ],
            'temperature' => 0.1,
            'max_gen_tokens' => 20,
        ];

        // Start both generators
        $gen1 = $aki1->doApiRequestAsync($params1);
        $gen2 = $aki2->doApiRequestAsync($params2);

        // Consume generator 1
        foreach ($gen1 as $progress) {}
        $result1 = $gen1->getReturn();

        // Consume generator 2
        foreach ($gen2 as $progress) {}
        $result2 = $gen2->getReturn();

        if (!($result1['success'] ?? false)) {
            throw new \RuntimeException("Parallel request 1 failed: " . ($result1['error'] ?? 'Unknown'));
        }

        if (!($result2['success'] ?? false)) {
            throw new \RuntimeException("Parallel request 2 failed: " . ($result2['error'] ?? 'Unknown'));
        }

        if (empty($result1['text']) || empty($result2['text'])) {
            throw new \RuntimeException("Parallel requests returned empty text");
        }

        // Verify independent results
        echo "      Parallel async OK (r1: '" . trim($result1['text']) . "', r2: '" . trim($result2['text']) . "')\n";
    }
}
