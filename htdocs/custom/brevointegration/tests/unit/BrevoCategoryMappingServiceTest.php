<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

dol_include_once('/brevointegration/class/services/brevocategorymappingservice.class.php');

if (!defined('MAIN_DB_PREFIX')) {
    define('MAIN_DB_PREFIX', 'llx_');
}

class FakeCategoryResult
{
    /** @var array<int,array<string,mixed>> */
    public $rows;

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }
}

class FakeCategoryDB extends DoliDB
{
    /** @var array<int,array<int,int>> */
    public $categoryLinks = array();

    /** @var array<int,array<string,mixed>> */
    public $categories = array();

    /**
     * @param string $sql
     * @return FakeCategoryResult
     */
    public function query($sql)
    {
        $upper = strtoupper($sql);
        if (strpos($upper, 'FROM '.strtoupper(MAIN_DB_PREFIX).'CATEGORIE_CONTACT') !== false) {
            if (preg_match('/FK_SOCPEOPLE=([0-9]+)/i', $upper, $matches)) {
                $contactId = (int) $matches[1];
            } else {
                $contactId = 0;
            }

            $rows = array();
            if (isset($this->categoryLinks[$contactId])) {
                foreach ($this->categoryLinks[$contactId] as $categoryId) {
                    $rows[] = array('category_id' => $categoryId);
                }
            }

            return new FakeCategoryResult($rows);
        }

        if (strpos($upper, 'WHERE C.ROWID IN') !== false) {
            $ids = array();
            if (preg_match('/IN \(([^)]+)\)/i', $sql, $matches)) {
                $parts = explode(',', $matches[1]);
                foreach ($parts as $part) {
                    $ids[] = (int) trim($part);
                }
            }

            $rows = array();
            foreach ($ids as $id) {
                if (isset($this->categories[$id])) {
                    $rows[] = array(
                        'rowid' => $id,
                        'label' => $this->categories[$id]['label'],
                    );
                }
            }

            return new FakeCategoryResult($rows);
        }

        if (strpos($upper, 'FROM '.strtoupper(MAIN_DB_PREFIX).'CATEGORIE AS C') !== false) {
            $entity = 1;
            if (preg_match('/ENTITY IN \(0,\s*([0-9]+)\)/i', $upper, $matches)) {
                $entity = (int) $matches[1];
            }
            $type = 4;
            if (preg_match('/TYPE=([0-9]+)/i', $upper, $typeMatches)) {
                $type = (int) $typeMatches[1];
            }

            $rows = array();
            foreach ($this->categories as $id => $data) {
                if ((int) $data['type'] !== $type) {
                    continue;
                }
                if ($data['entity'] !== 0 && (int) $data['entity'] !== $entity) {
                    continue;
                }
                $rows[] = array('rowid' => $id, 'label' => $data['label']);
            }

            return new FakeCategoryResult($rows);
        }

        return new FakeCategoryResult(array());
    }

    /**
     * @param FakeCategoryResult $result
     * @return object|false
     */
    public function fetch_object($result)
    {
        if (empty($result->rows)) {
            return false;
        }

        return (object) array_shift($result->rows);
    }

    /**
     * @param FakeCategoryResult $result
     * @return void
     */
    public function free($result)
    {
        $result->rows = array();
    }
}

class BrevoCategoryMappingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        if (!isset($GLOBALS['conf'])) {
            $GLOBALS['conf'] = new stdClass();
        }
        $GLOBALS['conf']->entity = 1;
        if (!isset($GLOBALS['conf']->global)) {
            $GLOBALS['conf']->global = new stdClass();
        }
        $GLOBALS['conf']->global->{BrevoCategoryMappingService::CONST_NAME} = '';
        $GLOBALS['dolibarr_const'] = array();
    }

    public function testSaveMappingsPersistsSanitizedEntries(): void
    {
        $db = new FakeCategoryDB();
        $service = new BrevoCategoryMappingService($db, $GLOBALS['conf']);

        $data = array(
            array('category_id' => 3, 'list_id' => 12),
            array('category_id' => '4', 'list_id' => '15'),
            array('category_id' => 0, 'list_id' => 99),
        );

        $this->assertTrue($service->saveMappings($data));
        $stored = $GLOBALS['conf']->global->{BrevoCategoryMappingService::CONST_NAME};
        $decoded = json_decode($stored, true);

        $this->assertSame(
            array(
                array('category_id' => 3, 'list_id' => 12),
                array('category_id' => 4, 'list_id' => 15),
            ),
            $decoded
        );
    }

    public function testGetListIdsForCategoriesReturnsUniqueValues(): void
    {
        $db = new FakeCategoryDB();
        $service = new BrevoCategoryMappingService($db, $GLOBALS['conf']);

        $GLOBALS['conf']->global->{BrevoCategoryMappingService::CONST_NAME} = json_encode(
            array(
                array('category_id' => 3, 'list_id' => 10),
                array('category_id' => 3, 'list_id' => 10),
                array('category_id' => 4, 'list_id' => 11),
            )
        );

        $listIds = $service->getListIdsForCategories(array(3, 4));
        sort($listIds);

        $this->assertSame(array(10, 11), $listIds);
    }

    public function testGetMappingsForContactIncludesLabels(): void
    {
        $db = new FakeCategoryDB();
        $db->categoryLinks = array(
            25 => array(3, 5),
        );
        $db->categories = array(
            3 => array('label' => 'VIP', 'type' => 4, 'entity' => 1),
            5 => array('label' => 'Prospect', 'type' => 4, 'entity' => 1),
            8 => array('label' => 'Other', 'type' => 2, 'entity' => 1),
        );

        $service = new BrevoCategoryMappingService($db, $GLOBALS['conf']);
        $GLOBALS['conf']->global->{BrevoCategoryMappingService::CONST_NAME} = json_encode(
            array(
                array('category_id' => 3, 'list_id' => 10),
                array('category_id' => 8, 'list_id' => 12),
            )
        );

        $mappings = $service->getMappingsForContact(25);

        $this->assertCount(1, $mappings);
        $this->assertSame(3, $mappings[0]['category_id']);
        $this->assertSame('VIP', $mappings[0]['category_label']);
        $this->assertSame(10, $mappings[0]['list_id']);
    }
}
