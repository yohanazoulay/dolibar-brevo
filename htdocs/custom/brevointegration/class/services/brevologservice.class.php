<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Service layer to persist and retrieve Brevo API call logs.
 */

dol_include_once('/brevointegration/class/brevolog.class.php');

/**
 * Class BrevoLogService
 */
class BrevoLogService
{
    /** @var DoliDB */
    private $db;

    /** @var Conf|stdClass */
    private $conf;

    /**
     * @param DoliDB     $db   Database handler
     * @param Conf|mixed $conf Global configuration
     */
    public function __construct($db, $conf)
    {
        $this->db = $db;
        $this->conf = $conf;
    }

    /**
     * Persist a Brevo API call log entry.
     *
     * @param string $method      HTTP method used
     * @param string $endpoint    Endpoint path
     * @param int    $httpCode    HTTP response code
     * @param int    $durationMs  Duration in milliseconds
     * @param bool   $success     Success flag
     * @param string $message     Optional error message
     * @return void
     */
    public function logRequest($method, $endpoint, $httpCode, $durationMs, $success, $message = '')
    {
        if (!defined('MAIN_DB_PREFIX')) {
            return;
        }

        if (!is_object($this->db) || !method_exists($this->db, 'query')) {
            return;
        }

        $log = new BrevoLog($this->db);
        $log->entity = $this->getEntity();
        $log->date_event = dol_now();
        $log->method = $this->truncate($method, 8);
        $log->endpoint = $this->truncate($endpoint, 255);
        $log->http_code = (int) $httpCode;
        $log->duration_ms = (int) $durationMs;
        $log->success = $success ? 1 : 0;
        $log->message = $message !== '' ? $this->truncate($message, 1024) : '';

        $result = $log->create();
        if ($result <= 0) {
            dol_syslog(__METHOD__.' failed to persist log: '.$log->error, LOG_WARNING);
        }
    }

    /**
     * Retrieve logs filtered by period and pagination.
     *
     * @param int|null    $startTimestamp Start timestamp (UTC)
     * @param int|null    $endTimestamp   End timestamp (UTC)
     * @param int         $limit          Results per page
     * @param int         $offset         Offset for pagination
     * @param string|null $sortfield      Field to sort by
     * @param string|null $sortorder      Sort order ASC|DESC
     * @return array{total:int,logs:array<int,array<string,mixed>>}
     */
    public function fetchLogs($startTimestamp, $endTimestamp, $limit, $offset, $sortfield = null, $sortorder = null)
    {
        if (!defined('MAIN_DB_PREFIX')) {
            return array('total' => 0, 'logs' => array());
        }

        if (!is_object($this->db) || !method_exists($this->db, 'query')) {
            return array('total' => 0, 'logs' => array());
        }

        $table = MAIN_DB_PREFIX.'brevo_log';
        $filters = array('entity='.(int) $this->getEntity());

        if ($startTimestamp !== null && method_exists($this->db, 'idate')) {
            $filters[] = 'date_event >= '.$this->db->idate($startTimestamp);
        } elseif ($startTimestamp !== null) {
            $filters[] = "date_event >= '".date('Y-m-d H:i:s', (int) $startTimestamp)."'";
        }

        if ($endTimestamp !== null && method_exists($this->db, 'idate')) {
            $filters[] = 'date_event <= '.$this->db->idate($endTimestamp);
        } elseif ($endTimestamp !== null) {
            $filters[] = "date_event <= '".date('Y-m-d H:i:s', (int) $endTimestamp)."'";
        }

        $whereClause = implode(' AND ', $filters);

        $countSql = 'SELECT COUNT(*) as total FROM '.$table.' WHERE '.$whereClause;
        $countResult = $this->db->query($countSql);
        if ($countResult === false) {
            dol_syslog(__METHOD__.' count query failed: '.$this->db->lasterror(), LOG_WARNING);

            return array('total' => 0, 'logs' => array());
        }

        $countObject = $this->db->fetch_object($countResult);
        $total = $countObject ? (int) $countObject->total : 0;
        if (method_exists($this->db, 'free')) {
            $this->db->free($countResult);
        }

        $allowedSortfields = array('date_event', 'method', 'http_code', 'duration_ms', 'success');
        if ($sortfield === null || !in_array($sortfield, $allowedSortfields, true)) {
            $sortfield = 'date_event';
        }
        $sortorder = strtoupper((string) $sortorder) === 'ASC' ? 'ASC' : 'DESC';

        $sql = 'SELECT rowid, date_event, method, endpoint, http_code, duration_ms, success, message';
        $sql .= ' FROM '.$table.' WHERE '.$whereClause;
        $sql .= ' ORDER BY '.$sortfield.' '.$sortorder;
        if (method_exists($this->db, 'plimit')) {
            $sql .= $this->db->plimit($limit, $offset);
        } else {
            $sql .= ' LIMIT '.(int) $limit.' OFFSET '.(int) $offset;
        }

        $resql = $this->db->query($sql);
        if ($resql === false) {
            dol_syslog(__METHOD__.' select query failed: '.$this->db->lasterror(), LOG_WARNING);

            return array('total' => $total, 'logs' => array());
        }

        $logs = array();
        while (true) {
            $obj = $this->db->fetch_object($resql);
            if (!$obj) {
                break;
            }

            $logs[] = array(
                'id' => isset($obj->rowid) ? (int) $obj->rowid : 0,
                'date_event' => $this->convertDate($obj->date_event),
                'method' => (string) $obj->method,
                'endpoint' => (string) $obj->endpoint,
                'http_code' => isset($obj->http_code) ? (int) $obj->http_code : 0,
                'duration_ms' => isset($obj->duration_ms) ? (int) $obj->duration_ms : 0,
                'success' => isset($obj->success) ? (int) $obj->success : 0,
                'message' => isset($obj->message) ? (string) $obj->message : ''
            );
        }

        if (method_exists($this->db, 'free')) {
            $this->db->free($resql);
        }

        return array('total' => $total, 'logs' => $logs);
    }

    /**
     * Convert SQL date to timestamp.
     *
     * @param mixed $dateValue Date value returned by the driver
     * @return int
     */
    private function convertDate($dateValue)
    {
        if (method_exists($this->db, 'jdate')) {
            return (int) $this->db->jdate($dateValue);
        }

        if (is_int($dateValue)) {
            return $dateValue;
        }

        return strtotime((string) $dateValue) ?: 0;
    }

    /**
     * Resolve active entity.
     *
     * @return int
     */
    private function getEntity()
    {
        if (is_object($this->conf) && isset($this->conf->entity)) {
            return (int) $this->conf->entity;
        }

        return 1;
    }

    /**
     * Multibyte-safe truncation helper.
     *
     * @param string $value  Input string
     * @param int    $length Maximum length
     * @return string
     */
    private function truncate($value, $length)
    {
        $value = (string) $value;
        $length = (int) $length;
        if ($length <= 0) {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value, 'UTF-8') > $length) {
                return mb_substr($value, 0, $length, 'UTF-8');
            }

            return $value;
        }

        if (strlen($value) > $length) {
            return substr($value, 0, $length);
        }

        return $value;
    }
}
