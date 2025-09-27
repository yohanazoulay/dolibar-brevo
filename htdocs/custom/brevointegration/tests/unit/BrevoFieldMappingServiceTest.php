<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

dol_include_once('/brevointegration/class/services/brevofieldmappingservice.class.php');

if (!defined('MAIN_DB_PREFIX')) {
    define('MAIN_DB_PREFIX', 'llx_');
}

if (!isset($GLOBALS['conf'])) {
    $GLOBALS['conf'] = new stdClass();
}
if (!isset($GLOBALS['conf']->entity)) {
    $GLOBALS['conf']->entity = 1;
}
if (!isset($GLOBALS['conf']->global)) {
    $GLOBALS['conf']->global = new stdClass();
}
if (!isset($GLOBALS['langs'])) {
    $GLOBALS['langs'] = new class() {
        public function trans($key)
        {
            return $key;
        }
    };
}

class FakeMappingDB extends DoliDB
{
}

class BrevoFieldMappingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        if (!isset($GLOBALS['conf']->global)) {
            $GLOBALS['conf']->global = new stdClass();
        }
        $GLOBALS['conf']->global->{BrevoFieldMappingService::CONST_NAME} = '';
        $GLOBALS['dolibarr_const'] = array();
        ExtraFields::$labels = array();
    }

    public function testGetDefaultMappingForContact(): void
    {
        $db = new FakeMappingDB();
        $service = new BrevoFieldMappingService($db, $GLOBALS['conf']);

        $mapping = $service->getMappingForType('contact');

        $this->assertSame(
            array(
                array('attribute' => 'FIRSTNAME', 'source' => 'standard', 'field' => 'firstname'),
                array('attribute' => 'LASTNAME', 'source' => 'standard', 'field' => 'lastname'),
            ),
            $mapping
        );
    }

    public function testSaveMappingPersistsConfiguration(): void
    {
        $db = new FakeMappingDB();
        $service = new BrevoFieldMappingService($db, $GLOBALS['conf']);

        $data = array(
            'contact' => array(
                array('attribute' => 'custom_name', 'source' => 'standard', 'field' => 'firstname'),
                array('attribute' => 'City', 'source' => 'extrafield', 'field' => 'city'),
            ),
            'thirdparty' => array(
                array('attribute' => 'company', 'source' => 'standard', 'field' => 'name'),
            ),
        );

        $this->assertTrue($service->saveMapping($data));

        $raw = $GLOBALS['conf']->global->{BrevoFieldMappingService::CONST_NAME};
        $decoded = json_decode($raw, true);

        $this->assertSame(
            array(
                'contact' => array(
                    array('attribute' => 'CUSTOM_NAME', 'source' => 'standard', 'field' => 'firstname'),
                    array('attribute' => 'CITY', 'source' => 'extrafield', 'field' => 'city'),
                ),
                'thirdparty' => array(
                    array('attribute' => 'COMPANY', 'source' => 'standard', 'field' => 'name'),
                ),
            ),
            $decoded
        );
    }

    public function testGetAvailableFieldsIncludesExtrafields(): void
    {
        $db = new FakeMappingDB();
        $service = new BrevoFieldMappingService($db, $GLOBALS['conf']);

        ExtraFields::$labels = array(
            'socpeople' => array('contactextra' => 'Contact extra'),
            'societe' => array('thirdextra' => 'Third extra'),
        );

        $contactFields = $service->getAvailableFields('contact');
        $this->assertArrayHasKey('standard', $contactFields);
        $this->assertArrayHasKey('extrafields', $contactFields);
        $this->assertSame('Contact extra', $contactFields['extrafields']['contactextra']);

        $thirdFields = $service->getAvailableFields('thirdparty');
        $this->assertSame('Third extra', $thirdFields['extrafields']['thirdextra']);
    }
}
