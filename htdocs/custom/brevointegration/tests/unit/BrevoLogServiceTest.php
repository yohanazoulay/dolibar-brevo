<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

dol_include_once('/brevointegration/class/services/brevologservice.class.php');

if (!defined('MAIN_DB_PREFIX')) {
    define('MAIN_DB_PREFIX', 'llx_');
}

class FakeBrevoLogResult
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

class FakeBrevoLogDB extends DoliDB
{
    /** @var array<int,array<string,mixed>> */
    public $data = array();

    /** @var string */
    public $lasterror = '';

    public function escape($value)
    {
        return addslashes((string) $value);
    }

    public function idate($timestamp)
    {
        return "'".date('Y-m-d H:i:s', (int) $timestamp)."'";
    }

    public function jdate($dateValue)
    {
        if (is_int($dateValue)) {
            return $dateValue;
        }

        return strtotime((string) $dateValue);
    }

    public function plimit($limit, $offset = 0)
    {
        return ' LIMIT '.(int) $limit.' OFFSET '.(int) $offset;
    }

    public function last_insert_id($table)
    {
        return count($this->data);
    }

    public function lasterror()
    {
        return $this->lasterror;
    }

    public function query($sql)
    {
        $upper = strtoupper($sql);
        if (strpos($upper, 'INSERT INTO') === 0) {
            return $this->handleInsert($sql);
        }
        if (strpos($upper, 'SELECT COUNT(*)') === 0) {
            return $this->handleCount($sql);
        }
        if (strpos($upper, 'SELECT ROWID') === 0) {
            return $this->handleSelect($sql);
        }

        $this->lasterror = 'Unsupported SQL: '.$sql;

        return false;
    }

    public function fetch_object($result)
    {
        if (empty($result->rows)) {
            return false;
        }

        return (object) array_shift($result->rows);
    }

    public function free($result)
    {
    }

    public function num_rows($result)
    {
        return count($result->rows);
    }

    private function handleInsert($sql)
    {
        if (!preg_match('/VALUES \((.+)\)$/i', trim($sql), $matches)) {
            $this->lasterror = 'Malformed INSERT';

            return false;
        }

        $values = $this->splitValues($matches[1]);

        $this->data[] = array(
            'rowid' => count($this->data) + 1,
            'entity' => (int) $values[0],
            'date_event' => strtotime(trim($values[1], "'")),
            'method' => stripslashes(trim($values[2], "'")),
            'endpoint' => stripslashes(trim($values[3], "'")),
            'http_code' => (int) $values[4],
            'duration_ms' => (int) $values[5],
            'success' => (int) $values[6],
            'message' => $values[7] === 'NULL' ? '' : stripslashes(trim($values[7], "'")),
        );

        return true;
    }

    private function handleCount($sql)
    {
        $filtered = $this->filterData($sql);

        return new FakeBrevoLogResult(array(array('total' => count($filtered))));
    }

    private function handleSelect($sql)
    {
        $filtered = $this->filterData($sql);
        $limit = $this->parseLimit($sql);
        $offset = $this->parseOffset($sql);

        usort($filtered, static function ($a, $b) {
            if ($a['date_event'] === $b['date_event']) {
                return 0;
            }

            return ($a['date_event'] > $b['date_event']) ? -1 : 1;
        });

        $slice = array_slice($filtered, $offset, $limit);
        $rows = array();
        foreach ($slice as $row) {
            $rows[] = array(
                'rowid' => $row['rowid'],
                'date_event' => date('Y-m-d H:i:s', $row['date_event']),
                'method' => $row['method'],
                'endpoint' => $row['endpoint'],
                'http_code' => $row['http_code'],
                'duration_ms' => $row['duration_ms'],
                'success' => $row['success'],
                'message' => $row['message'],
            );
        }

        return new FakeBrevoLogResult($rows);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function filterData($sql)
    {
        $entity = $this->extractEntity($sql);
        $from = $this->extractDateBoundary($sql, '>=');
        $to = $this->extractDateBoundary($sql, '<=');

        $results = array();
        foreach ($this->data as $row) {
            if ($entity !== null && $row['entity'] !== $entity) {
                continue;
            }
            if ($from !== null && $row['date_event'] < $from) {
                continue;
            }
            if ($to !== null && $row['date_event'] > $to) {
                continue;
            }
            $results[] = $row;
        }

        return $results;
    }

    private function extractEntity($sql)
    {
        if (preg_match('/entity=([0-9]+)/', $sql, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractDateBoundary($sql, $operator)
    {
        $pattern = '/date_event '.preg_quote($operator, '/')." \\s*'([0-9\-: ]+)'/";
        if (preg_match($pattern, $sql, $matches)) {
            return strtotime($matches[1]);
        }

        return null;
    }

    private function parseLimit($sql)
    {
        if (preg_match('/LIMIT ([0-9]+)/i', $sql, $matches)) {
            return (int) $matches[1];
        }

        return PHP_INT_MAX;
    }

    private function parseOffset($sql)
    {
        if (preg_match('/OFFSET ([0-9]+)/i', $sql, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @return array<int,string>
     */
    private function splitValues($valuesString)
    {
        $values = array();
        $buffer = '';
        $inString = false;
        $length = strlen($valuesString);
        for ($i = 0; $i < $length; $i++) {
            $char = $valuesString[$i];
            if ($char === "'" && ($i === 0 || $valuesString[$i - 1] !== '\\')) {
                $inString = !$inString;
                $buffer .= $char;
                continue;
            }
            if ($char === ',' && !$inString) {
                $values[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if ($buffer !== '') {
            $values[] = trim($buffer);
        }

        return $values;
    }
}

/**
 * @covers BrevoLogService
 */
class BrevoLogServiceTest extends TestCase
{
    public function testLogRequestPersistsEntry(): void
    {
        $db = new FakeBrevoLogDB();
        $conf = new stdClass();
        $conf->entity = 3;

        $service = new BrevoLogService($db, $conf);
        $service->logRequest('POST', '/contacts', 201, 245, true, '');

        $this->assertCount(1, $db->data);
        $row = $db->data[0];
        $this->assertSame(3, $row['entity']);
        $this->assertSame('POST', $row['method']);
        $this->assertSame('/contacts', $row['endpoint']);
        $this->assertSame(201, $row['http_code']);
        $this->assertSame(245, $row['duration_ms']);
        $this->assertSame(1, $row['success']);
    }

    public function testFetchLogsFiltersByPeriod(): void
    {
        $db = new FakeBrevoLogDB();
        $conf = new stdClass();
        $conf->entity = 1;

        $reference = strtotime('2024-05-27 12:00:00');
        $db->data = array(
            array(
                'rowid' => 1,
                'entity' => 1,
                'date_event' => $reference,
                'method' => 'GET',
                'endpoint' => '/account',
                'http_code' => 200,
                'duration_ms' => 110,
                'success' => 1,
                'message' => ''
            ),
            array(
                'rowid' => 2,
                'entity' => 1,
                'date_event' => $reference - 86400,
                'method' => 'POST',
                'endpoint' => '/contacts',
                'http_code' => 500,
                'duration_ms' => 980,
                'success' => 0,
                'message' => 'Server error'
            ),
        );

        $service = new BrevoLogService($db, $conf);
        $result = $service->fetchLogs($reference - 3600, $reference + 3600, 25, 0, 'date_event', 'DESC');

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['logs']);
        $this->assertSame('/account', $result['logs'][0]['endpoint']);
    }
}
