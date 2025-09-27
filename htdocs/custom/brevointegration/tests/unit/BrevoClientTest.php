<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

dol_include_once('/brevointegration/class/BrevoClient.class.php');

/**
 * @covers BrevoClient
 */
class BrevoClientTest extends TestCase
{
    public function testRequestWithoutApiKeyReturnsError(): void
    {
        $client = new BrevoClient(new DoliDB(), new stdClass(), '');
        $response = $client->request('GET', '/v3/account');

        $this->assertFalse($response['success']);
        $this->assertSame('Missing API key', $response['error']);
    }

    public function testUpsertContactCastsListIdsToIntegers(): void
    {
        $client = new class(new DoliDB(), new stdClass(), 'abc') extends BrevoClient {
            public $lastPayload;
            public $lastEndpoint;

            public function request(string $method, string $endpoint, array $headers = array(), ?array $payload = null): array
            {
                $this->lastPayload = $payload;
                $this->lastEndpoint = $endpoint;

                return array('success' => true, 'http_code' => 200, 'duration_ms' => 0, 'data' => array(), 'error' => null);
            }
        };

        $client->upsertContact('john@example.com', array('FIRSTNAME' => 'John'), array('12', 34));

        $this->assertSame(array(12, 34), $client->lastPayload['listIds']);
        $this->assertSame('/v3/contacts', $client->lastEndpoint);
    }

    public function testGetListUsesExpectedEndpoint(): void
    {
        $client = new class(new DoliDB(), new stdClass(), 'abc') extends BrevoClient {
            public $lastEndpoint;

            public function request(string $method, string $endpoint, array $headers = array(), ?array $payload = null): array
            {
                $this->lastEndpoint = $endpoint;

                return array('success' => true, 'http_code' => 200, 'duration_ms' => 0, 'data' => array(), 'error' => null);
            }
        };

        $client->getList(42);

        $this->assertSame('/v3/contacts/lists/42', $client->lastEndpoint);
    }
}
