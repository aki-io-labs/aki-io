<?php
/**
 * AKI.IO PHP Test Runner
 * 
 * Runs all tests sequentially with proper dependency handling:
 * 1. Image Generation (creates test images for multimodal tests)
 * 2. LLM Tests (simple, stream)
 * 3. Multimodal Tests (image edit, vision)
 * 4. TTS Tests
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Aki.php';
require_once __DIR__ . '/../src/functions.php';

use AkiIO\Aki;

class TestRunner
{
    private string $apiKey;
    private array $results = [];
    private float $startTime;
    private int $passCount = 0;
    private int $failCount = 0;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->startTime = microtime(true);
    }

    public function run(): void
    {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║         AKI.IO PHP Client - Comprehensive Test Suite         ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";

        // Phase 1: Image Generation (creates test images for multimodal tests)
        $this->runPhase('PHASE 1: Image Generation', [
            'ImageGenTest'
        ]);

        // Phase 2: LLM Tests
        $this->runPhase('PHASE 2: LLM Tests', [
            'LLM/LLMSimpleTest',
            'LLM/LLMAsyncTest',
            'LLM/LLMStreamTest',
        ]);

        // Phase 3: Multimodal Tests (depends on Phase 1)
        $this->runPhase('PHASE 3: Multimodal Tests', [
            'Image/ImageEditTest',
            'Vision/VisionTest',
        ]);

        // Phase 4: TTS Tests
        $this->runPhase('PHASE 4: TTS Tests', [
            'TTS/TTSSyncTest'
        ]);

        // Phase 5: Edge Case Tests
        $this->runPhase('PHASE 5: Edge Case Tests', [
            'Edge/TimeoutTest',
            'Edge/ErrorHandlingTest',
            'Edge/BinaryEncodingTest',
            'Edge/CancellationTest',
            'Edge/ProgressCallbackTest',
        ]);

        $this->printSummary();
    }

    private function runPhase(string $phaseName, array $tests): void
    {
        echo "┌─────────────────────────────────────────────────────────────────┐\n";
        echo "│ {$phaseName}" . str_repeat(' ', 61 - strlen($phaseName)) . "│\n";
        echo "└─────────────────────────────────────────────────────────────────┘\n";

        foreach ($tests as $testName) {
            $this->runTest($testName);
        }
        echo "\n";
    }

    private function runTest(string $testName): void
    {
        $testFile = __DIR__ . "/{$testName}.php";

        if (!file_exists($testFile)) {
            echo "  ⚠️  {$testName}: SKIPPED (file not found)\n";
            $this->results[$testName] = ['status' => 'skipped', 'error' => 'File not found'];
            return;
        }

        $testStart = microtime(true);
        echo "  🔄  {$testName}: Running...\r";

        try {
            require_once $testFile;

            // Extract just the class name (last segment after any /)
            $segments = explode('/', $testName);
            $className = end($segments);
            $testClass = "AkiIO\\Tests\\{$className}";
            if (class_exists($testClass)) {
                $test = new $testClass($this->apiKey);
                $test->run();
                
                $duration = round(microtime(true) - $testStart, 2);
                echo "  ✅  {$testName}: PASSED ({$duration}s)     \n";
                $this->results[$testName] = ['status' => 'passed', 'duration' => $duration];
                $this->passCount++;
            } else {
                throw new \RuntimeException("Test class {$testClass} not found");
            }
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $testStart, 2);
            echo "  ❌  {$testName}: FAILED ({$duration}s)     \n";
            echo "     Error: {$e->getMessage()}\n";
            $this->results[$testName] = ['status' => 'failed', 'duration' => $duration, 'error' => $e->getMessage()];
            $this->failCount++;
        }
    }

    private function printSummary(): void
    {
        $totalDuration = round(microtime(true) - $this->startTime, 2);
        
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║                      TEST SUMMARY                            ║\n";
        echo "╠══════════════════════════════════════════════════════════════╣\n";
        printf("║  Total Tests: %-3d  │  Passed: \033[32m%-3d\033[0m  │  Failed: \033[31m%-3d\033[0m  │  Time: %5.1fs  ║\n",
            $this->passCount + $this->failCount, $this->passCount, $this->failCount, $totalDuration);
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";

        if ($this->failCount > 0) {
            echo "Failed tests:\n";
            foreach ($this->results as $name => $result) {
                if ($result['status'] === 'failed') {
                    echo "  - {$name}: {$result['error']}\n";
                }
            }
        }
    }

    public function getResults(): array
    {
        return $this->results;
    }
}

// Run if executed directly
if (php_sapi_name() === 'cli' && basename($argv[0] ?? '') === basename(__FILE__)) {
    $apiKey = getenv('AKI_API_KEY') ?: $argv[1] ?? null;

    if (empty($apiKey)) {
        echo "Error: No API key provided. Set AKI_API_KEY environment variable or pass as argument.\n";
        echo "Usage: php TestRunner.php [API_KEY]\n";
        exit(1);
    }

    $runner = new TestRunner($apiKey);
    $runner->run();
}
