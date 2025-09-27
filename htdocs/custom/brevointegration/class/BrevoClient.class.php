<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Lightweight HTTP client for Brevo REST API.
 */

/**
 * Minimal HTTP client to communicate with Brevo REST API.
 */
class BrevoClient
{
    /** @var DoliDB */
    private $db;

    /** @var Conf */
    private $conf;

    /** @var string */
    private $apiKey;

    /**
     * @param DoliDB $db     Database handler
     * @param Conf   $conf   Global configuration
     * @param string $apiKey Brevo API key
     */
    public function __construct($db, $conf, string $apiKey)
    {
        $this->db = $db;
        $this->conf = $conf;
        $this->apiKey = trim($apiKey);
    }

    /**
     * Validate an API key by querying the Brevo account endpoint.
     *
     * @param string $apiKey API key to validate
     * @return array<string,mixed>
     */
    public function validateApiKey(string $apiKey): array
    {
        $previous = $this->apiKey;
        $this->apiKey = trim($apiKey);

        $response = $this->request('GET', '/v3/account');

        $this->apiKey = $previous;

        return $response;
    }

    /**
     * Retrieve Brevo contact lists.
     *
     * @param int $limit  Number of lists to fetch
     * @param int $offset Offset for pagination
     * @return array<string,mixed>
     */
    public function getLists(int $limit = 50, int $offset = 0): array
    {
        $query = http_build_query(array('limit' => $limit, 'offset' => $offset));

        return $this->request('GET', '/v3/contacts/lists'.($query !== '' ? '?'.$query : ''));
    }

    /**
     * Retrieve a specific Brevo contact list.
     *
     * @param int $listId Identifier of the list
     * @return array<string,mixed>
     */
    public function getList(int $listId): array
    {
        return $this->request('GET', '/v3/contacts/lists/'.(int) $listId);
    }

    /**
     * Create or update a contact in Brevo.
     *
     * @param string               $email      Contact email address
     * @param array<string,mixed>  $attributes Contact attributes
     * @param array<int,int>       $listIds    List identifiers
     * @return array<string,mixed>
     */
    public function upsertContact(string $email, array $attributes, array $listIds): array
    {
        $payload = array(
            'email' => $email,
            'attributes' => $attributes,
            'listIds' => array_map('intval', $listIds),
            'updateEnabled' => true,
        );

        return $this->request('POST', '/v3/contacts', array(), $payload);
    }

    /**
     * Remove contacts from a Brevo list.
     *
     * @param int             $listId List identifier
     * @param array<int,string> $emails Email addresses to remove
     * @return array<string,mixed>
     */
    public function removeContactsFromList(int $listId, array $emails): array
    {
        $payload = array('emails' => array_values($emails));

        return $this->request('POST', '/v3/contacts/lists/'.(int) $listId.'/contacts/remove', array(), $payload);
    }

    /**
     * Perform an HTTP request against the Brevo API.
     *
     * @param string          $method   HTTP method (GET, POST, ...)
     * @param string          $endpoint Endpoint path beginning with /v3
     * @param array<int,string> $headers Additional headers
     * @param array<string,mixed>|null $payload Optional JSON payload
     * @return array<string,mixed>
     */
    public function request(string $method, string $endpoint, array $headers = array(), ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            return $this->buildErrorResponse('Missing PHP cURL extension');
        }

        if (!function_exists('json_decode')) {
            return $this->buildErrorResponse('Missing PHP JSON extension');
        }

        if ($this->apiKey === '') {
            return $this->buildErrorResponse('Missing API key');
        }

        $url = $this->buildUrl($endpoint);
        $curl = curl_init($url);
        if ($curl === false) {
            return $this->buildErrorResponse('Unable to initialize cURL');
        }

        $method = strtoupper($method);
        $httpHeaders = array_merge(
            array(
                'Accept: application/json',
                'api-key: '.$this->apiKey,
            ),
            $headers
        );

        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $httpHeaders,
        );

        if ($payload !== null) {
            $httpHeaders[] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER] = $httpHeaders;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
        }

        $startTime = microtime(true);
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $curlErrorNumber = curl_errno($curl);
        $curlErrorMessage = $curlErrorNumber ? curl_error($curl) : '';
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        curl_close($curl);

        if ($curlErrorNumber !== 0) {
            $message = $curlErrorMessage !== '' ? $curlErrorMessage : 'Network error';

            return array(
                'success' => false,
                'http_code' => 0,
                'duration_ms' => $durationMs,
                'data' => null,
                'error' => $message,
            );
        }

        $decoded = null;
        if ($body !== false && $body !== '') {
            $decoded = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = null;
            }
        }

        $success = $httpCode >= 200 && $httpCode < 300;
        $errorMessage = null;
        if (!$success) {
            if (is_array($decoded)) {
                if (isset($decoded['message']) && is_string($decoded['message'])) {
                    $errorMessage = $decoded['message'];
                } elseif (isset($decoded['error']) && is_string($decoded['error'])) {
                    $errorMessage = $decoded['error'];
                }
            }
            if ($errorMessage === null) {
                $errorMessage = 'HTTP '.$httpCode;
            }
        }

        return array(
            'success' => $success,
            'http_code' => $httpCode,
            'duration_ms' => $durationMs,
            'data' => $decoded,
            'error' => $errorMessage,
        );
    }

    /**
     * Build a full URL for a Brevo endpoint.
     *
     * @param string $endpoint Endpoint beginning with /v3
     * @return string
     */
    private function buildUrl(string $endpoint): string
    {
        if ($endpoint === '') {
            return 'https://api.brevo.com/v3';
        }

        if (strpos($endpoint, 'http') === 0) {
            return $endpoint;
        }

        if ($endpoint[0] !== '/') {
            $endpoint = '/'.$endpoint;
        }

        return 'https://api.brevo.com'.$endpoint;
    }

    /**
     * Build an error response with sane defaults.
     *
     * @param string $message Error message
     * @return array<string,mixed>
     */
    private function buildErrorResponse(string $message): array
    {
        return array(
            'success' => false,
            'http_code' => 0,
            'duration_ms' => 0,
            'data' => null,
            'error' => $message,
        );
    }
}

