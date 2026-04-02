# AKI.IO API Interface - PHP Client

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

> **High-performance AI model services via simple API interfaces.**  
> Official PHP client library for connecting to the [AKI.IO](https://aki.io) platform.

## Overview

This PHP client library provides a simple interface for integrating with the AKI.IO platform. AKI.IO offers access to leading open-source AI models for LLM, image generation, and text-to-speech.

## Installation

### Via Composer (recommended)

```bash
composer require aki-io/aki-io
```

For async request support (optional):

```bash
composer require symfony/http-client
```

### Manual

```php
require_once 'path/to/src/Aki.php';
require_once 'path/to/src/functions.php';
```

## Quick Start

### Simple LLM Request

```php
<?php
use AkiIO\Aki;

$aki = new Aki('llama3_8b_chat', 'your-api-key');

$result = $aki->doApiRequest([
    'chat_context' => [
        ['role' => 'user', 'content' => 'Tell me a joke']
    ],
    'max_gen_tokens' => 200,
]);

if ($result['success']) {
    echo $result['text'];
}
```

### With Progress Streaming

```php
<?php
use AkiIO\Aki;

$aki = new Aki('llama3_8b_chat', 'your-api-key');

$outputPosition = 0;

$result = $aki->doApiRequest([
    'chat_context' => [
        ['role' => 'user', 'content' => 'Write a short story']
    ],
], function ($progressInfo, $progressData) use (&$outputPosition) {
    if ($progressData && isset($progressData['text'])) {
        echo substr($progressData['text'], $outputPosition);
        $outputPosition = strlen($progressData['text']);
    }
});
```

### Image Generation

```php
<?php
use AkiIO\Aki;

$aki = new Aki('z_image_turbo', 'your-api-key');

$result = $aki->doApiRequest([
    'prompt' => 'A cute robot on a beach',
    'width' => 512,
    'height' => 512,
]);

foreach ($result['images'] ?? [] as $idx => $imageData) {
    $decoded = Aki::decodeBinary($imageData);
    file_put_contents("image_{$idx}.png", $decoded[1]);
}
```

### Text-to-Speech
```php
<?php
use AkiIO\Aki;

$aki = new Aki('xttsv2', 'your-api-key');

$result = $aki->doApiRequest([
    'text' => 'Hello, world!',
    'language' => 'en',
    'voice' => 'emma',
]);

$decoded = Aki::decodeBinary($result['audio']);
file_put_contents('output.wav', $decoded[1]);
```

### Async Requests (requires `symfony/http-client`)

```bash
composer require symfony/http-client
```

**Blocking wrapper** — simple async call that returns the final result:

```php
<?php
use AkiIO\Aki;

$aki = new Aki('llama3_8b_chat', 'your-api-key');

$result = $aki->doApiRequestAwait([
    'chat_context' => [
        ['role' => 'user', 'content' => 'Tell me a joke']
    ],
], function ($progressInfo, $progressData) {
    echo "Progress: {$progressInfo['progress']}%\n";
});

echo $result['text'];
```

**Generator-based** — full control over progress yields:

```php
<?php
use AkiIO\Aki;

$aki = new Aki('llama3_8b_chat', 'your-api-key');

$generator = $aki->doApiRequestAsync([
    'chat_context' => [
        ['role' => 'user', 'content' => 'Tell me a joke']
    ],
], function ($progressInfo, $progressData) {
    // Handle progress updates
});

foreach ($generator as $progress) {
    // Yields progress updates as they arrive
}

$result = $generator->getReturn(); // Final result
```

**Parallel requests** — run multiple requests concurrently:

```php
<?php
$aki1 = new Aki('llama3_8b_chat', 'your-api-key');
$aki2 = new Aki('z_image_turbo', 'your-api-key');

$gen1 = $aki1->doApiRequestAsync(['chat_context' => [...]]);
$gen2 = $aki2->doApiRequestAsync(['prompt' => 'A sunset']);

// Start both, then consume results
foreach ($gen1 as $progress) {}
$result1 = $gen1->getReturn();

foreach ($gen2 as $progress) {}
$result2 = $gen2->getReturn();
```

## API Methods

| Method | Description |
|-------|-------------|
| `doApiRequest($params, $progressCallback)` | Synchronous API request |
| `doApiRequestAsync($params, $progressCallback)` | Async request (returns Generator) |
| `doApiRequestAwait($params, $progressCallback)` | Blocking wrapper around async |
| `getEndpointList($apiKey)` | Get available endpoints |
| `getEndpointDetails($name, $apiKey)` | Get endpoint details |
| `initApiKey($apiKey)` | Validate API key |
| `cancelRequest($jobId)` | Cancel running request |
| `appendProgressInputParams($jobId, $params)` | Append params to progress polling |

## Static Helpers

| Method | Description |
|-------|-------------|
| `encodeBinary($data, $format)` | Encode binary to base64 with MIME header |
| `decodeBinary($base64)` | Decode base64 to binary `[format, data]` |
| `checkIfValidBase64String($string)` | Validate base64 string |
| `getVersion()` | Get client version |

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `api_server` | string | `https://aki.io` | API base URL |
| `output_binary_format` | string | `base64` | Binary output format (`base64` or `raw`) |
| `raise_exceptions` | bool | `false` | Throw exceptions on errors |
| `progress_interval` | float | `0.2` | Progress polling interval (seconds) |

## Running Tests

```bash
cd php
composer install                           # install dependencies
AKI_API_KEY=your-key php tests/TestRunner.php
```

## Examples

See `examples/` directory for complete examples:
- `llm_simple_example.php` - Basic LLM request
- `llm_stream_example.php` - LLM with streaming
- `llm_async_example.php` - Async/await LLM request
- `image_generation_example.php` - Image generation
- `tts_example.php` - Text-to-speech

## Requirements

- PHP 8.2+
- ext-curl
- ext-json
- `symfony/http-client` (optional, for async requests)

## License

MIT License - see [LICENSE](../LICENSE) file for details.
