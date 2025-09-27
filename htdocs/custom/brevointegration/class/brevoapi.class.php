<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Wrapper around Brevo REST API.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
dol_include_once('/brevointegration/class/services/brevologservice.class.php');

if (!function_exists('dol_buildpath')) {
    require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
}

/**
 * Class BrevoApi
 */
class BrevoApi
{
    /** @var DoliDB */
    private $db;

    /** @var Conf */
    private $conf;

    /** @var string */
    private $apiKey = '';

    /** @var BrevoLogService|null */
    private $logService;

    /**
     * @param DoliDB $db   Database handler
     * @param Conf   $conf Global configuration
     * @param string $apiKey API key
     * @param mixed  $logService Optional log service dependency
     */
    public function __construct($db, $conf, $apiKey = '', $logService = null)
    {
        $this->db = $db;
        $this->conf = $conf;
        $this->apiKey = trim($apiKey);
        $this->logService = $logService !== null ? $logService : new BrevoLogService($db, $conf);
    }

    /**
     * Update API key at runtime
     *
     * @param string $apiKey API key
     * @return void
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = trim((string) $apiKey);
    }

    /**
     * Validate an API key by calling Brevo account endpoint.
     *
     * @param string $apiKey API key to validate
     * @return array
     */
    public function validateApiKey($apiKey)
    {
        $previous = $this->apiKey;
        $this->setApiKey($apiKey);

        try {
            $response = $this->request('GET', '/account');
        } catch (Throwable $exception) {
            $response = $this->formatError('Unexpected client error: '.$exception->getMessage());
        }

        $this->setApiKey($previous);

        return $response;
    }

    /**
     * Retrieve Brevo contact lists
     *
     * @param int $limit  Number of results
     * @param int $offset Offset
     * @return array
     */
    public function getLists($limit = 50, $offset = 0)
    {
        $query = http_build_query(array('limit' => $limit, 'offset' => $offset));
        $endpoint = '/contacts/lists?'.$query;

        return $this->request('GET', $endpoint);
    }

    /**
     * Retrieve a single Brevo contact list
     *
     * @param int $listId List identifier
     * @return array
     */
    public function getList($listId)
    {
        $endpoint = '/contacts/lists/'.(int) $listId;

        return $this->request('GET', $endpoint);
    }

    /**
     * Create or update a contact in Brevo
     *
     * @param string $email      Contact email
     * @param array  $attributes Attributes (FIRSTNAME, LASTNAME...)
     * @param array  $listIds    Target list IDs
     * @return array
     */
    public function upsertContact($email, array $attributes, array $listIds)
    {
        $payload = array(
            'email' => $email,
            'attributes' => $attributes,
            'listIds' => array_map('intval', $listIds),
            'updateEnabled' => true,
        );

        return $this->request('POST', '/contacts', $payload);
    }

    /**
     * Remove a contact from a Brevo list
     *
     * @param int   $listId List ID
     * @param array $emails Email addresses to remove
     * @return array
     */
    public function removeContactsFromList($listId, array $emails)
    {
        $payload = array('emails' => array_values($emails));
        $endpoint = '/contacts/lists/'.(int) $listId.'/contacts/remove';

        return $this->request('POST', $endpoint, $payload);
    }

    /**
     * Perform HTTP request against Brevo API.
     *
     * @param string     $method   HTTP method
     * @param string     $endpoint API endpoint
     * @param array|null $payload  Payload
     * @return array
     */
    protected function request($method, $endpoint, $payload = null)
    {
        $url = 'https://api.brevo.com/v3'.$endpoint;
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: '.$this->apiKey,
        );

        if (empty($this->apiKey)) {
            $this->recordLog($method, $endpoint, 0, 0, false, 'Missing API key');

            return $this->formatError('Missing API key');
        }

        if (!function_exists('curl_init')) {
            $this->recordLog($method, $endpoint, 0, 0, false, 'Missing PHP cURL extension');

            return $this->formatError('Missing PHP cURL extension');
        }

        if (!$this->isJsonExtensionAvailable()) {
            $this->recordLog($method, $endpoint, 0, 0, false, 'Missing PHP JSON extension');

            return $this->formatError('Missing PHP JSON extension');
        }

        $method = strtoupper((string) $method);
        $startTime = microtime(true);

        try {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('Unable to initialize curl');
            }

            $payloadString = $payload ? json_encode($payload) : '';

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadString);
            }

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response === false) {
                $message = $error !== '' ? $error : 'Unknown curl error';
                $this->recordLog($method, $endpoint, isset($info['http_code']) ? (int) $info['http_code'] : 0, $durationMs, false, $message);

                return $this->formatError($message);
            }

            $decoded = json_decode($response, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                $message = 'Invalid JSON response: '.json_last_error_msg();
                $this->recordLog($method, $endpoint, isset($info['http_code']) ? (int) $info['http_code'] : 0, $durationMs, false, $message);

                return $this->formatError($message);
            }

            $success = isset($info['http_code']) && $info['http_code'] >= 200 && $info['http_code'] < 300;
            if (!$success) {
                $message = isset($decoded['message']) ? $decoded['message'] : 'Unexpected HTTP status '.$info['http_code'];

                $this->recordLog($method, $endpoint, isset($info['http_code']) ? (int) $info['http_code'] : 0, $durationMs, false, $message);

                return $this->formatError($message, $info['http_code'], $decoded);
            }

            $this->recordLog($method, $endpoint, isset($info['http_code']) ? (int) $info['http_code'] : 200, $durationMs, true);

            return array(
                'success' => true,
                'http_code' => isset($info['http_code']) ? (int) $info['http_code'] : 200,
                'data' => $decoded,
            );
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $this->recordLog($method, $endpoint, 0, $durationMs, false, $exception->getMessage());

            return $this->formatError('Unexpected client error: '.$exception->getMessage());
        }
    }

    /**
     * Check whether the JSON extension is available.
     *
     * @return bool
     */
    protected function isJsonExtensionAvailable()
    {
        return function_exists('json_encode') && function_exists('json_decode');
    }

    /**
     * Format error response
     *
     * @param string     $message Error message
     * @param int        $code    HTTP status code
     * @param array|null $data    Additional data
     * @return array
     */
    protected function formatError($message, $code = 0, $data = null)
    {
        dol_syslog(__METHOD__.' '.$message, LOG_WARNING);

        return array(
            'success' => false,
            'http_code' => (int) $code,
            'error' => $message,
            'data' => $data,
        );
    }

    /**
     * Record request in Brevo logs if service available.
     *
     * @param string $method     HTTP method
     * @param string $endpoint   Endpoint path
     * @param int    $httpCode   HTTP status code
     * @param int    $durationMs Duration in milliseconds
     * @param bool   $success    Success flag
     * @param string $message    Optional message
     * @return void
     */
    private function recordLog($method, $endpoint, $httpCode, $durationMs, $success, $message = '')
    {
        if ($this->logService && method_exists($this->logService, 'logRequest')) {
            try {
                $this->logService->logRequest($method, $endpoint, $httpCode, $durationMs, $success, $message);
            } catch (Throwable $exception) {
                dol_syslog(__METHOD__.' unable to record log: '.$exception->getMessage(), LOG_WARNING);
            }
        }
    }
}
