<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

dol_include_once('/brevointegration/class/brevoapi.class.php');

class NullBrevoLogService
{
    /** @var array */
    public $records = array();

    public function logRequest($method, $endpoint, $httpCode, $durationMs, $success, $message = '')
    {
        $this->records[] = array(
            'method' => $method,
            'endpoint' => $endpoint,
            'httpCode' => $httpCode,
            'durationMs' => $durationMs,
            'success' => $success,
            'message' => $message,
        );
    }
}

/**
 * @covers BrevoApi
 */
class BrevoApiTest extends TestCase
{
    public function testGetListsWithoutApiKeyReturnsError(): void
    {
        $logService = new NullBrevoLogService();
        $api = new BrevoApi(new DoliDB(), new stdClass(), '', $logService);
        $response = $api->getLists();

        $this->assertFalse($response['success']);
        $this->assertSame('Missing API key', $response['error']);
        $this->assertSame('Missing API key', $logService->records[0]['message']);
    }

    public function testUpsertContactCastsListIdsToIntegers(): void
    {
        $api = new class(new DoliDB(), new stdClass(), 'abc', new NullBrevoLogService()) extends BrevoApi {
            public $lastPayload;

            protected function request($method, $endpoint, $payload = null)
            {
                $this->lastPayload = $payload;

                return array('success' => true, 'http_code' => 200, 'data' => array());
            }
        };

        $api->upsertContact('john@example.com', array('FIRSTNAME' => 'John'), array('12', 34));

        $this->assertSame(array(12, 34), $api->lastPayload['listIds']);
    }

    public function testGetListUsesExpectedEndpoint(): void
    {
        $api = new class(new DoliDB(), new stdClass(), 'abc', new NullBrevoLogService()) extends BrevoApi {
            public $lastEndpoint;

            protected function request($method, $endpoint, $payload = null)
            {
                $this->lastEndpoint = $endpoint;

                return array('success' => true, 'http_code' => 200, 'data' => array());
            }
        };

        $api->getList(42);

        $this->assertSame('/contacts/lists/42', $api->lastEndpoint);
    }
}
