<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

dol_include_once('/brevo-par-Meditrust/class/brevosync.class.php');

if (!defined('MAIN_DB_PREFIX')) {
    define('MAIN_DB_PREFIX', 'llx_');
}

if (!isset($GLOBALS['conf'])) {
    $GLOBALS['conf'] = new stdClass();
}
$GLOBALS['conf']->entity = 1;

class FakeResult
{
    /** @var array */
    public $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }
}

class FakeDoliDB extends DoliDB
{
    public $data = array();
    public $lastResult;
    public $lasterror = '';
    private $lastId = 0;

    public function begin()
    {
    }

    public function commit()
    {
    }

    public function rollback()
    {
    }

    public function escape($value)
    {
        return addslashes((string) $value);
    }

    public function idate($timestamp)
    {
        return "'".date('Y-m-d H:i:s', (int) $timestamp)."'";
    }

    public function last_insert_id($table)
    {
        return $this->lastId;
    }

    public function lasterror()
    {
        return $this->lasterror;
    }

    public function query($sql)
    {
        if (stripos($sql, 'SELECT rowid FROM') === 0) {
            return $this->handleSelectRowId($sql);
        }
        if (stripos($sql, 'INSERT INTO') === 0) {
            return $this->handleInsert($sql);
        }
        if (stripos($sql, 'UPDATE') === 0) {
            return $this->handleUpdate($sql);
        }
        if (stripos($sql, 'SELECT rowid,') === 0) {
            return $this->handleSelectList($sql);
        }

        $this->lasterror = 'Unsupported SQL';
        return false;
    }

    private function handleSelectRowId($sql)
    {
        $conditions = $this->parseConditions($sql);
        $matches = array();
        foreach ($this->data as $row) {
            if ($row['entity'] == $conditions['entity'] &&
                $row['fk_socpeople'] == $conditions['fk_socpeople'] &&
                $row['fk_societe'] == $conditions['fk_societe'] &&
                $row['brevo_list_id'] == $conditions['brevo_list_id']) {
                $matches[] = (object) array('rowid' => $row['rowid']);
            }
        }
        $this->lastResult = new FakeResult($matches);

        return $this->lastResult;
    }

    private function handleInsert($sql)
    {
        if (!preg_match('/VALUES \((.+)\)/', $sql, $matches)) {
            $this->lasterror = 'Malformed INSERT';
            return false;
        }
        $values = explode(',', $matches[1]);
        $this->lastId++;
        $this->data[] = array(
            'rowid' => $this->lastId,
            'entity' => (int) $values[0],
            'fk_socpeople' => (int) $values[1],
            'fk_societe' => (int) $values[2],
            'brevo_list_id' => (int) $values[3],
            'brevo_contact_id' => trim($values[4], "'"),
            'date_sync' => strtotime(trim($values[5], "'")),
            'status' => trim($values[6], "'"),
        );

        return true;
    }

    private function handleUpdate($sql)
    {
        $conditions = $this->parseConditions($sql);
        foreach ($this->data as &$row) {
            if (($row['rowid'] == $conditions['rowid']) || (
                $row['entity'] == $conditions['entity'] &&
                $row['fk_socpeople'] == $conditions['fk_socpeople'] &&
                $row['brevo_list_id'] == $conditions['brevo_list_id'] &&
                ($conditions['fk_societe'] === null || $row['fk_societe'] == $conditions['fk_societe'])
            )) {
                if (isset($conditions['status'])) {
                    $row['status'] = $conditions['status'];
                }
                if (isset($conditions['brevo_contact_id'])) {
                    $row['brevo_contact_id'] = $conditions['brevo_contact_id'];
                }
                if (isset($conditions['date_sync'])) {
                    $row['date_sync'] = $conditions['date_sync'];
                }
            }
        }
        unset($row);

        return true;
    }

    private function handleSelectList($sql)
    {
        $conditions = $this->parseConditions($sql);
        $matches = array();
        foreach ($this->data as $row) {
            if ($row['entity'] == $conditions['entity'] &&
                $row['fk_socpeople'] == $conditions['fk_socpeople'] &&
                ($conditions['fk_societe'] === null || $row['fk_societe'] == $conditions['fk_societe'])) {
                $matches[] = (object) array(
                    'rowid' => $row['rowid'],
                    'fk_socpeople' => $row['fk_socpeople'],
                    'fk_societe' => $row['fk_societe'],
                    'brevo_list_id' => $row['brevo_list_id'],
                    'brevo_contact_id' => $row['brevo_contact_id'],
                    'date_sync' => date('Y-m-d H:i:s', $row['date_sync']),
                    'status' => $row['status'],
                );
            }
        }
        $this->lastResult = new FakeResult($matches);

        return $this->lastResult;
    }

    private function parseConditions($sql)
    {
        $conditions = array(
            'entity' => 1,
            'fk_socpeople' => 0,
            'fk_societe' => null,
            'brevo_list_id' => 0,
            'rowid' => null,
            'status' => null,
            'brevo_contact_id' => null,
            'date_sync' => null,
        );
        if (preg_match('/entity=([0-9]+)/', $sql, $m)) {
            $conditions['entity'] = (int) $m[1];
        }
        if (preg_match('/fk_socpeople=([0-9]+)/', $sql, $m)) {
            $conditions['fk_socpeople'] = (int) $m[1];
        }
        if (preg_match('/fk_societe=([0-9]+)/', $sql, $m)) {
            $conditions['fk_societe'] = (int) $m[1];
        }
        if (preg_match('/brevo_list_id=([0-9]+)/', $sql, $m)) {
            $conditions['brevo_list_id'] = (int) $m[1];
        }
        if (preg_match('/rowid=([0-9]+)/', $sql, $m)) {
            $conditions['rowid'] = (int) $m[1];
        }
        if (preg_match("/status='([^']+)'/", $sql, $m)) {
            $conditions['status'] = $m[1];
        }
        if (preg_match("/brevo_contact_id='([^']+)'/", $sql, $m)) {
            $conditions['brevo_contact_id'] = $m[1];
        }
        if (preg_match("/date_sync='([^']+)'/", $sql, $m)) {
            $conditions['date_sync'] = strtotime($m[1]);
        }

        return $conditions;
    }

    public function fetch_object($result)
    {
        return array_shift($result->rows);
    }

    public function free($result)
    {
    }

    public function jdate($date)
    {
        return strtotime($date);
    }
}

/**
 * @covers BrevoSync
 */
class BrevoSyncTest extends TestCase
{
    public function testCreateAndMarkRemoved(): void
    {
        $db = new FakeDoliDB();
        $sync = new BrevoSync($db);
        $user = new User();

        $sync->fk_socpeople = 1;
        $sync->fk_societe = 2;
        $sync->brevo_list_id = 10;
        $sync->brevo_contact_id = 'ABC';

        $id = $sync->create($user);
        $this->assertGreaterThan(0, $id);
        $this->assertSame('ok', $db->data[0]['status']);

        $entries = $sync->fetchByContact(1, 2);
        $this->assertCount(1, $entries);
        $this->assertSame(10, $entries[0]['brevo_list_id']);

        $sync->markRemoved(1, 2, 10);
        $this->assertSame('removed', $db->data[0]['status']);
    }
}
