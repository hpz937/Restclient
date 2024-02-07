<?php

namespace Hpz937\Restclient;

use Exception;

/**
 * Class RestClient
 *
 * A versatile PHP class for making HTTP requests using cURL with options for handling JSON responses and cookies.
 */
class RestClient {
    /**
     * @var string|null The base URL for API requests.
     */
    private $baseUrl;

    /**
     * @var array The HTTP headers to include in requests.
     */
    private $headers;

    /**
     * @var bool Whether to use cookies for maintaining session.
     */
    private $useCookies;

    /**
     * @var string|null The HTTP response from the most recent request.
     */
    private $response;

    /**
     * @var string|null The user-agent string to use in HTTP requests.
     */
    private $userAgent;

    /**
     * @var string|null The filename to use when downloading a file.
     */
    private $downloadFilename;

    /**
     * @var string|null The file path to use for storing cookies.
     */
    private $cookieFilePath;

    /**
     * RestClient constructor.
     *
     * @param bool $useCookies Whether to use cookies for maintaining session (default: false).
     */
    public function __construct($cookieFilePath = '/tmp/rest_client_cookies.txt') {
        $this->headers = [
            'Content-Type: application/json',
            // Add any other headers you need
        ];
        $this->useCookies = false;
        $this->cookieFilePath = $cookieFilePath;
    }

    /**
     * Set the base URL for API requests.
     *
     * @param string $baseUrl The base URL for API requests.
     * @return RestClient $this Allows method chaining.
     */
    public function setBaseUrl($baseUrl) {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * Set whether to use cookies for maintaining session.
     *
     * @param bool $useCookies Whether to use cookies (default: false).
     * @return RestClient $this Allows method chaining.
     */
    public function setUseCookies($useCookies) {
        $this->useCookies = $useCookies;
        return $this;
    }

    /**
     * Set the user-agent string for HTTP requests.
     *
     * @param string $userAgent The user-agent string to use.
     * @return RestClient $this Allows method chaining.
     */
    public function setUserAgent($userAgent) {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Set custom HTTP headers for requests.
     *
     * @param array $headers An array of custom HTTP headers.
     * @return RestClient $this Allows method chaining.
     */
    public function setHeaders($headers) {
        $this->headers = $headers;
    }

    /**
     * Make an HTTP GET request.
     *
     * @param string $endpoint The API endpoint to request.
     * @return RestClient $this Allows method chaining.
     */
    public function get($endpoint) {
        $this->response = $this->request('GET', $endpoint);
        return $this;
    }

    /**
     * Make an HTTP POST request.
     *
     * @param string $endpoint The API endpoint to request.
     * @param mixed $data The data to include in the POST request body.
     * @return RestClient $this Allows method chaining.
     */
    public function post($endpoint, $data) {
        $this->response = $this->request('POST', $endpoint, $data);
        return $this;
    }

    /**
     * Make an HTTP PUT request.
     *
     * @param string $endpoint The API endpoint to request.
     * @param mixed $data The data to include in the PUT request body.
     * @return RestClient $this Allows method chaining.
     */
    public function put($endpoint, $data) {
        $this->response = $this->request('PUT', $endpoint, $data);
        return $this;
    }

    /**
     * Make an HTTP DELETE request.
     *
     * @param string $endpoint The API endpoint to request.
     * @return RestClient $this Allows method chaining.
     */
    public function delete($endpoint) {
        $this->response = $this->request('DELETE', $endpoint);
        return $this;
    }

    /**
     * Decode the JSON response to an associative array.
     *
     * @return array The decoded JSON response.
     * @throws Exception If there is no response to decode or if there is a decoding error.
     */
    public function decodeJson() {
        if ($this->response === null) {
            throw new Exception('No response to decode. Make a request first.');
        }
        return $this->jsonToArray($this->response);
    }

    public function download($filename) {
        $this->downloadFilename = $filename;
        return $this;
    }

    /**
     * Get the raw HTTP response.
     *
     * @return string The raw HTTP response.
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     * Send an HTTP request.
     *
     * @param string $method The HTTP request method (GET, POST, PUT, DELETE, etc.).
     * @param string $endpoint The API endpoint to request.
     * @param mixed $data The data to include in the request body.
     * @return string The HTTP response.
     * @throws Exception If there is a cURL error, an HTTP error, or a JSON decoding error.
     */
    private function request($method, $endpoint, $data = null) {
        // Check if endpoint already contains http:// or https://
        if (!preg_match("~^(?:f|ht)tps?://~i", $endpoint)) {
            // Prepend base URL if endpoint doesn't contain http:// or https://
            $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        } else {
            // Use endpoint as is
            $url = $endpoint;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Set the user-agent if provided
        if ($this->userAgent) {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        }

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

        if ($this->useCookies) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFilePath);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFilePath);
        }

        if ($this->downloadFilename) {
            // Create directory if it doesn't exist
            $downloadDir = dirname($this->downloadFilename);
            if (!file_exists($downloadDir)) {
                mkdir($downloadDir, 0777, true);
            }

            // Set option to write response to a file
            $fileHandle = fopen($this->downloadFilename, 'w');
            curl_setopt($ch, CURLOPT_FILE, $fileHandle);
        }

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception('HTTP error: ' . $httpCode);
        }

        if ($this->downloadFilename && !$response) {
            return false; // File download failed
        } elseif ($this->downloadFilename) {
            fclose($fileHandle);
            return true; // File download successful
        }

        return $response;
    }

    /**
     * Convert JSON string to an associative array.
     *
     * @param string $json The JSON string to decode.
     * @return array The decoded JSON as an associative array.
     * @throws Exception If there is a JSON decoding error.
     */
    private function jsonToArray($json) {
        $data = json_decode($json, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decoding error: ' . json_last_error_msg());
        }

        return $data;
    }
}
