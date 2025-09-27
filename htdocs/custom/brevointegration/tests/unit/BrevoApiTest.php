<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

dol_include_once('/brevointegration/class/brevoapi.class.php');

/**
 * @covers BrevoApi
 */
class BrevoApiTest extends TestCase
{
    public function testGetListsWithoutApiKeyReturnsError(): void
    {
        $api = new BrevoApi(new DoliDB(), new stdClass(), '');
        $response = $api->getLists();

        $this->assertFalse($response['success']);
        $this->assertSame('Missing API key', $response['error']);
    }

    public function testUpsertContactCastsListIdsToIntegers(): void
    {
        $api = new class(new DoliDB(), new stdClass(), 'abc') extends BrevoApi {
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
}
