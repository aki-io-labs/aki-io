<?php
/**
 * AKI.IO Model Hub API PHP interface
 *
 * Copyright (c) AKI.IO GmbH and affiliates. Find more info at https://aki.io
 *
 * This software may be used and distributed according to the terms of the MIT LICENSE
 */

namespace AkiIO;

/**
 * Custom exception for permission-related errors.
 */
class PermissionException extends \RuntimeException {}

/**
 * An interface for interacting with the AKI.IO AI model hub services.
 */
class Aki
{
    private string $endpointName;
    private ?string $apiKey;
    private string $apiServerUrl;
    private float $progressInterval;
    private bool $raiseExceptions;
    private string $outputBinaryFormat;
    private bool $returnToolCallDict;
    private array $canceledJobs = [];
    private array $progressInputParams = [];

    private const DEFAULT_PROGRESS_INTERVAL = 0.2;
    private const VERSION = 'PHP AKI.IO Client 1.0.0';
    private const IMAGE_FORMATS = ['png', 'jpeg', 'jpg', 'webp', 'tiff', 'gif', 'bmp'];
    private const AUDIO_FORMATS = ['wav', 'mp3', 'ogg', 'flac'];

    /**
     * Constructor for the Aki class.
     *
     * @param string $endpointName The name of the API endpoint
     * @param string|null $apiKey The API key, register at https://aki.io
     * @param array $options Configuration options:
     */
    public function __construct(
        string $endpointName,
        ?string $apiKey = null,
        array $options = []
    ) {
        $this->endpointName = $endpointName;
        $this->apiKey = $apiKey;
        $this->apiServerUrl = ($options['api_server'] ?? 'https://aki.io') . '/api/';
        $this->outputBinaryFormat = $options['output_binary_format'] ?? 'base64';
        $this->raiseExceptions = $options['raise_exceptions'] ?? false;
        $this->progressInterval = $options['progress_interval'] ?? self::DEFAULT_PROGRESS_INTERVAL;
        $this->returnToolCallDict = $options['return_tool_call_dict'] ?? false;
    }

    /**
     * Do a synchronous API request with optional progress callback.
     *
     * @param array $params Request parameters
     * @param callable|null $progressCallback Progress callback function
     * @return array Response data
     */
    public function doApiRequest(array $params, ?callable $progressCallback = null): array
    {
        $url = $this->apiServerUrl . 'call/' . $this->endpointName;
        $params['key'] = $this->apiKey;
        $params['wait_for_result'] = $progressCallback === null;
        $params = $this->serializeJsonValues($params);
        $result = $this->fetchSync($url, $params);

        if ($progressCallback !== null && ($result['success'] ?? false)) {
            $jobId = $result['job_id'];
            $progressCallback([
                'job_id' => $jobId,
                'progress' => 0,
                'queue_position' => -1,
                'estimate' => -1
            ], null);
            $result = $this->pollUntilComplete($jobId, $progressCallback);
        } else {
            $result = $this->convertResultParams($result);
        }

        return $result;
    }

    /**
     * Initialize and validate API key.
     *
     * @param string|null $apiKey API key to validate
     * @return array Validation response
     */
    public function initApiKey(?string $apiKey = null): array
    {
        $this->apiKey = $apiKey ?? $this->apiKey;
        return $this->fetchSync($this->apiServerUrl . 'validate_key', [
            'version' => self::VERSION,
            'key' => $this->apiKey
        ], false);
    }

    /**
     * Get list of available endpoints.
     *
     * @param string|null $apiKey Optional API key
     * @return array List of endpoints
     */
    public function getEndpointList(?string $apiKey = null): array
    {
        $result = $this->fetchSync($this->apiServerUrl . 'endpoints', [
            'key' => $apiKey ?? $this->apiKey
        ], false);
        return $result['endpoints'] ?? $result;
    }

    /**
     * Get details for a specific endpoint.
     *
     * @param string $endpointName Endpoint name
     * @param string|null $apiKey Optional API key
     * @return array Endpoint details
     */
    public function getEndpointDetails(string $endpointName, ?string $apiKey = null): array
    {
        return $this->fetchSync($this->apiServerUrl . 'endpoints/' . $endpointName, [
            'key' => $apiKey ?? $this->apiKey
        ], false);
    }

    /**
     * Cancel a running request.
     *
     * @param string|null $jobId Job ID to cancel,     */
    public function cancelRequest(?string $jobId = null): void
    {
        $this->canceledJobs[] = $jobId ?? 'all';
    }

    /**
     * Append progress input parameters.
     *
     * @param string $jobId Job ID
     * @param array $params Parameters to append
     */
    public function appendProgressInputParams(string $jobId, array $params): void
    {
        if (!isset($this->progressInputParams[$jobId])) {
            $this->progressInputParams[$jobId] = [];
        }
        $this->progressInputParams[$jobId][] = $params;
    }

    /**
     * Set API key.
     *
     * @param string $apiKey API key
     */
    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Encode binary data to base64 with MIME header.
     *
     * @param string $binaryData Raw binary data
     * @param string $mediaFormat Media format
     * @param string|null $mediaType Media type
     * @return string Base64 encoded string
     */
    public static function encodeBinary(string $binaryData, string $mediaFormat = 'octet-stream', ?string $mediaType = null): string
    {
        $mediaFormat = strtolower($mediaFormat);
        $mediaType = $mediaType ?? self::detectMediaTypeFromFormat($mediaFormat);
        return 'data:' . $mediaType . '/' . $mediaFormat . ';base64,' . base64_encode($binaryData);
    }

    /**
     * Decode base64 data to binary.
     *
     * @param string $base64Data Base64 encoded data
     * @return array [format, binaryData]
     */
    public static function decodeBinary(string $base64Data): array
    {
        if (str_starts_with($base64Data, 'data:')) {
            if (preg_match('/^data:([^\/]+)\/([^;,]+);base64,(.+)$/', $base64Data, $matches)) {
                return [$matches[2], base64_decode($matches[3])];
            }
            $parts = explode(',', $base64Data, 2);
            if (count($parts) === 2) {
                return ['octet-stream', base64_decode($parts[1])];
            }
            return ['octet-stream', base64_decode($base64Data)];
        }
        if (strlen($base64Data) > 0) {
            return ['octet-stream', base64_decode($base64Data)];
        }
        return [null, null];
    }

    /**
     * Check if string is valid base64.
     *
     * @param string $testString String to test
     * @return bool True if valid base64
     */
    public static function checkIfValidBase64String(string $testString): bool
    {
        $body = str_contains($testString, ',') 
            ? explode(',', $testString, 2)[1] 
            : null;
        
        if ($body === null || $body === '') {
            return false;
        }
        
        return base64_encode(base64_decode($body, true)) === $body;
    }

    /**
     * Check if string is valid JSON.
     *
     * @param string $testString String to test
     * @return bool True if valid JSON
     */
    public static function checkIfValidJsonString(string $testString): bool
    {
        json_decode($testString, true);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get client version.
     *
     * @return string Version string
     */
    public static function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Detect media type from format.
     *
     * @param string $mediaFormat Media format
     * @return string Media type
     */
    public static function detectMediaTypeFromFormat(string $mediaFormat): string
    {
        $format = strtolower($mediaFormat);
        if (in_array($format, self::IMAGE_FORMATS)) return 'image';
        if (in_array($format, self::AUDIO_FORMATS)) return 'audio';
        return 'octet-stream';
    }

    /**
     * Perform synchronous HTTP request.
     */
    private function fetchSync(string $url, array $params, bool $doPost = true): array
    {
        $ch = curl_init();

        if ($doPost) {
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($params),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            ]);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return $this->handleError(null, 'api', $curlError, 0);
        }

        $responseData = json_decode($response, true) ?? [];

        if ($httpCode !== 200) {
            return $this->handleError($responseData, 'api', null, $httpCode);
        }

        return $responseData;
    }

    /**
     * Fetch progress for a job.
     */
    private function fetchProgress(string $jobId): array
    {
        $params = [
            'key' => $this->apiKey,
            'job_id' => $jobId
        ];

        if (isset($this->progressInputParams[$jobId])) {
            $params = array_merge($params, ...$this->progressInputParams[$jobId]);
            unset($this->progressInputParams[$jobId]);
        }

        $cancelKey = array_search($jobId, $this->canceledJobs);
        if ($cancelKey !== false) {
            $params['cancel'] = true;
            array_splice($this->canceledJobs, $cancelKey, 1);
        } elseif (($allKey = array_search('all', $this->canceledJobs)) !== false) {
            $params['cancel'] = true;
            array_splice($this->canceledJobs, $allKey, 1);
        }

        return $this->fetchSync($this->apiServerUrl . 'progress/' . $this->endpointName, $params);
    }

    /**
     * Poll until request is complete.
     */
    private function pollUntilComplete(string $jobId, callable $progressCallback): array
    {
        while (true) {
            $result = $this->fetchProgress($jobId);
            $jobState = $result['job_state'] ?? '';
            $progress = $result['progress'] ?? [];

            [$progressInfo, $progressData] = $this->processProgress($result);

            $isComplete = in_array($jobState, ['done', 'canceled', 'lapsed']) && empty($progress);

            if ($isComplete) {
                return $progressData;
            }

            $progressCallback($progressInfo, $progressData);
            usleep((int)($this->progressInterval * 1000000));
        }
    }

    /**
     * Process progress result.
     */
    private function processProgress(array $result): array
    {
        $progressInfo = ['job_id' => $result['job_id'] ?? null];
        $progressData = [];
        $jobState = $result['job_state'] ?? '';

        if ($result['success'] ?? false) {
            if (in_array($jobState, ['done', 'canceled']) && empty($result['progress'])) {
                $progressData = $result['job_result'] ?? [];
                if ($jobState === 'canceled') {
                    $progressData['job_state'] = $jobState;
                }
                $progressInfo['success'] = true;
                $progressInfo['job_state'] = $jobState;
                $progressInfo['progress'] = 100;
            } else {
                $progress = $result['progress'] ?? [];
                $progressInfo['progress'] = $progress['progress'] ?? 0;
                $progressInfo['queue_position'] = $progress['queue_position'] ?? -1;
                $progressInfo['estimate'] = $progress['estimate'] ?? -1;
                $progressInfo['job_state'] = $jobState;
                $progressInfo['success'] = true;
                $progressData = $progress['progress_data'] ?? [];
            }
            return [$progressInfo, $this->convertResultParams($progressData)];
        }
        return [$progressInfo, $progressData];
    }

    /**
     * Handle error response.
     */
    private function handleError(?array $response, string $requestType, ?string $errorMessage, int $statusCode): array
    {
        $responseJson = [
            'success' => false,
            'error' => $errorMessage ?? $response['error'] ?? 'Unknown error',
            'error_code' => $statusCode ?: ($response['error_code'] ?? 400)
        ];

        if ($response && isset($response['error']) && is_array($response['error'])) {
            $responseJson['error'] = implode(';', $response['error']);
        }

        if ($this->raiseExceptions) {
            $msg = ucfirst($requestType) . " request at {$this->apiServerUrl} failed!\nHTTP status code: {$statusCode}\nError message: {$responseJson['error']}";
            if ($statusCode >= 500 || $statusCode === 404) {
                throw new \RuntimeException($msg);
            } elseif ($statusCode === 400) {
                throw new \InvalidArgumentException($msg);
            } elseif ($statusCode > 400 && $statusCode < 500) {
                throw new PermissionException("Permission denied: " . $msg);
            }
        }

        return $responseJson;
    }

    /**
     * Convert result parameters.
     */
    private function convertResultParams(array $params): array
    {
        if (empty($params)) return [];

        $converted = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $converted[$key] = array_map(fn($v, $k = '') => $this->convertBase64($v, $k), $value);
            } elseif (is_string($value)) {
                $converted[$key] = $this->convertBase64($value, $key);
            } else {
                $converted[$key] = $value;
            }
        }
        return $converted;
    }

    /**
     * Convert base64 value based on output format.
     */
    private function convertBase64(string $value, string $key = ''): mixed
    {
        if ($this->outputBinaryFormat === 'raw' && self::checkIfValidBase64String($value)) {
            $decoded = self::decodeBinary($value);
            return $decoded[1] ?? $value;
        }
        
        if ($this->returnToolCallDict && $key === 'tool_calls' && self::checkIfValidJsonString($value)) {
            return json_decode($value, true);
        }
        
        return $value;
    }

    /**
     * Serialize JSON values in parameters.
     */
    private function serializeJsonValues(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $params[$key] = json_encode($value);
            }
        }
        return $params;
    }
}
