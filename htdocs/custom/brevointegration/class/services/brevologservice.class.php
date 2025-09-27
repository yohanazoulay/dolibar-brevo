<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Service layer to persist and retrieve Brevo API call logs.
 */

dol_include_once('/brevointegration/class/services/brevodatabasemaintenanceservice.class.php');

/**
 * Class BrevoLogService
 */
class BrevoLogService
{
    /** @var DoliDB */
    private $db;

    /** @var Conf|stdClass */
    private $conf;

    /** @var array{exists:bool,fields:array<string,string>}|null */
    private $logTableSchema = null;

    /** @var BrevoDatabaseMaintenanceService */
    private $maintenanceService;

    /**
     * @param DoliDB     $db   Database handler
     * @param Conf|mixed $conf Global configuration
     */
    public function __construct($db, $conf)
    {
        $this->db = $db;
        $this->conf = $conf;
        $this->maintenanceService = new BrevoDatabaseMaintenanceService($db);
    }

    /**
     * Record a Brevo API call in the database.
     *
     * @param string $method      HTTP method used
     * @param string $endpoint    Endpoint path
     * @param int    $httpCode    HTTP response code
     * @param int    $durationMs  Duration in milliseconds
     * @param bool   $success     Success flag
     * @param string $message     Optional error message
     * @return void
     */
    public function record(string $method, string $endpoint, int $httpCode, int $durationMs, bool $success, string $message = ''): void
    {
        if (!defined('MAIN_DB_PREFIX')) {
            return;
        }

        if (!is_object($this->db) || !method_exists($this->db, 'query')) {
            return;
        }

        $status = $this->getLogStorageStatus();
        if (!$status['ready']) {
            return;
        }

        $now = dol_now();
        $dateSql = method_exists($this->db, 'idate') ? $this->db->idate($now) : "'".date('Y-m-d H:i:s', (int) $now)."'";

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."brevo_log (entity, date_event, method, endpoint, http_code, duration_ms, success, message) VALUES (";
        $sql .= (int) $this->getEntity().',';
        $sql .= $dateSql.',';
        $sql .= "'".$this->db->escape($this->truncate($method, 8))."',";
        $sql .= "'".$this->db->escape($this->truncate($endpoint, 255))."',";
        $sql .= (int) $httpCode.',';
        $sql .= (int) $durationMs.',';
        $sql .= ($success ? 1 : 0).',';
        if ($message !== '') {
            $sql .= "'".$this->db->escape($this->truncate($message, 1024))."'";
        } else {
            $sql .= 'NULL';
        }
        $sql .= ')';

        try {
            $resql = $this->db->query($sql);
            if ($resql === false) {
                dol_syslog(__METHOD__.' SQL error: '.$this->db->lasterror(), LOG_ERR);
            }
        } catch (Throwable $exception) {
            dol_syslog(__METHOD__.' exception: '.$exception->getMessage(), LOG_ERR);
        }
    }

    /**
     * Backward compatibility wrapper.
     *
     * @param string $method
     * @param string $endpoint
     * @param int    $httpCode
     * @param int    $durationMs
     * @param bool   $success
     * @param string $message
     * @return void
     */
    public function logRequest($method, $endpoint, $httpCode, $durationMs, $success, $message = '')
    {
        $this->record((string) $method, (string) $endpoint, (int) $httpCode, (int) $durationMs, (bool) $success, (string) $message);
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

        $status = $this->getLogStorageStatus();
        if (!$status['ready']) {
            return array('total' => 0, 'logs' => array());
        }

        $schema = $this->describeLogTable();
        $fields = $schema['fields'];
        $table = $this->getLogTableName();

        $filters = array();
        if (isset($fields['entity'])) {
            $filters[] = $fields['entity'].'='.(int) $this->getEntity();
        }

        if ($startTimestamp !== null && isset($fields['date_event'])) {
            $startSql = $this->formatDateForSql($startTimestamp);
            if ($startSql !== null) {
                $filters[] = $fields['date_event'].' >= '.$startSql;
            }
        }

        if ($endTimestamp !== null && isset($fields['date_event'])) {
            $endSql = $this->formatDateForSql($endTimestamp);
            if ($endSql !== null) {
                $filters[] = $fields['date_event'].' <= '.$endSql;
            }
        }

        $whereClause = !empty($filters) ? implode(' AND ', $filters) : '1=1';

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

        $allowedSortfields = array();
        foreach (array('date_event', 'method', 'http_code', 'duration_ms', 'success') as $candidate) {
            if (isset($fields[$candidate])) {
                $allowedSortfields[] = $candidate;
            }
        }

        if ($sortfield === null || !in_array($sortfield, $allowedSortfields, true)) {
            $sortfield = !empty($allowedSortfields) ? $allowedSortfields[0] : null;
        }
        $sortorder = strtoupper((string) $sortorder) === 'ASC' ? 'ASC' : 'DESC';

        $selectMap = $this->buildSelectMap($fields);
        if (empty($selectMap)) {
            return array('total' => $total, 'logs' => array());
        }

        $selectParts = array();
        foreach ($selectMap as $alias => $columnName) {
            $selectParts[] = $columnName.' AS '.$alias;
        }

        $sql = 'SELECT '.implode(', ', $selectParts).' FROM '.$table.' WHERE '.$whereClause;
        if ($sortfield !== null) {
            $sql .= ' ORDER BY '.$fields[$sortfield].' '.$sortorder;
        }
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
                'id' => isset($obj->id) ? (int) $obj->id : 0,
                'date_event' => isset($obj->date_event) ? $this->convertDate($obj->date_event) : 0,
                'method' => isset($obj->method) ? (string) $obj->method : '',
                'endpoint' => isset($obj->endpoint) ? (string) $obj->endpoint : '',
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
     * Expose the current state of the Brevo log storage table.
     *
     * @return array{table_name:string,exists:bool,ready:bool,missing_columns:array<int,string>,available_columns:array<int,string>}
     */
    public function getLogStorageStatus()
    {
        $defaultStatus = array(
            'table_name' => $this->maintenanceService->getLogTableName(),
            'exists' => false,
            'ready' => false,
            'missing_columns' => array(),
            'available_columns' => array()
        );

        try {
            $status = $this->maintenanceService->getLogTableStatus();
        } catch (Throwable $exception) {
            dol_syslog(__METHOD__.' unexpected exception: '.$exception->getMessage(), LOG_ERR);
            $status = $defaultStatus;
        }

        if (!is_array($status)) {
            dol_syslog(__METHOD__.' invalid status payload type: '.gettype($status), LOG_ERR);
            $status = $defaultStatus;
        } else {
            $status = array_merge($defaultStatus, $status);
        }

        $status['exists'] = !empty($status['exists']);
        $status['missing_columns'] = $this->sanitizeColumnList($status['missing_columns'], 'missing_columns');
        $status['available_columns'] = $this->sanitizeColumnList($status['available_columns'], 'available_columns');
        $status['ready'] = $status['exists'] && empty($status['missing_columns']);

        $fields = !empty($status['available_columns']) ? $this->normalizeFieldMap($status['available_columns']) : array();
        $this->logTableSchema = array('exists' => $status['exists'], 'fields' => $fields);

        return $status;
    }

    /**
     * Normalise arbitrary payloads into a list of column names.
     *
     * @param mixed  $value   Raw payload
     * @param string $context Context identifier for logging
     * @return array<int,string>
     */
    private function sanitizeColumnList($value, $context)
    {
        if (is_array($value)) {
            $columns = array();
            foreach ($value as $column) {
                if ($column === null || $column === '') {
                    continue;
                }

                $columns[] = (string) $column;
            }

            return $columns;
        }

        if ($value === null || $value === '') {
            dol_syslog(__METHOD__.' unexpected empty '.$context.' payload', LOG_WARNING);

            return array();
        }

        dol_syslog(__METHOD__.' unexpected '.$context.' payload type: '.gettype($value), LOG_WARNING);

        return array((string) $value);
    }

    /**
     * Execute a full INSERT/UPDATE/DELETE cycle on the log table to ensure write access.
     *
     * @return array{
     *     supported:bool,
     *     success:bool,
     *     error:string,
     *     error_details:string,
     *     operations:array<string,array{success:bool,message:string}>
     * }
     */
    public function testLogTableWriteOperations()
    {
        $result = array(
            'supported' => false,
            'success' => false,
            'error' => '',
            'error_details' => '',
            'operations' => array(
                'insert' => array('success' => false, 'message' => ''),
                'update' => array('success' => false, 'message' => ''),
                'delete' => array('success' => false, 'message' => ''),
            ),
        );

        if (!is_object($this->db) || !method_exists($this->db, 'query')) {
            $result['error'] = 'db_unavailable';

            return $result;
        }

        $status = $this->getLogStorageStatus();
        if (!$status['exists']) {
            $result['error'] = 'log_table_missing';

            return $result;
        }

        if (!$status['ready']) {
            $result['error'] = 'log_table_incomplete';

            return $result;
        }

        $schema = $this->describeLogTable();
        $fields = $schema['fields'];
        $requiredColumns = $this->getRequiredLogColumns();
        foreach ($requiredColumns as $column) {
            if (!isset($fields[$column])) {
                $result['error'] = 'missing_column';
                $result['error_details'] = $column;

                return $result;
            }
        }

        $result['supported'] = true;

        $table = $this->getLogTableName();
        $operations = &$result['operations'];
        $token = uniqid('brevo_diag_', true);
        $initialMessage = 'BREVO_DIAG_INSERT '.$token;
        $updatedMessage = 'BREVO_DIAG_UPDATE '.$token;
        $rowId = null;
        $transactionOpened = false;

        if (method_exists($this->db, 'begin')) {
            $transactionOpened = (bool) $this->db->begin();
        }

        try {
            $timestamp = dol_now();
            $dateSql = $this->formatDateForSql($timestamp);
            if ($dateSql === null) {
                $dateSql = "'".date('Y-m-d H:i:s', (int) $timestamp)."'";
            }

            $columns = array(
                $fields['entity'] => (string) (int) $this->getEntity(),
                $fields['date_event'] => $dateSql,
                $fields['method'] => "'".$this->db->escape($this->truncate('DIAG', 8))."'",
                $fields['endpoint'] => "'".$this->db->escape($this->truncate('/brevointegration/diagnostic', 255))."'",
                $fields['http_code'] => '200',
                $fields['duration_ms'] => '1',
                $fields['success'] => '1',
                $fields['message'] => "'".$this->db->escape($this->truncate($initialMessage, 1024))."'",
            );

            $insertSql = 'INSERT INTO '.$table.' ('.implode(', ', array_keys($columns)).') VALUES ('.implode(', ', array_values($columns)).')';
            $insertRes = $this->db->query($insertSql);
            if ($insertRes === false) {
                $operations['insert']['message'] = $this->db->lasterror();
            } else {
                $operations['insert']['success'] = true;
                if (method_exists($this->db, 'last_insert_id')) {
                    $rowId = (int) $this->db->last_insert_id($table);
                }

                if ($rowId <= 0) {
                    $limit = method_exists($this->db, 'plimit') ? $this->db->plimit(1, 0) : ' LIMIT 1';
                    $selectSql = 'SELECT '.$fields['rowid'].' AS id FROM '.$table.' WHERE '.$fields['message']."='".$this->db->escape($this->truncate($initialMessage, 1024))."'".$limit;
                    $selectRes = $this->db->query($selectSql);
                    if ($selectRes !== false) {
                        $object = $this->db->fetch_object($selectRes);
                        if ($object && isset($object->id)) {
                            $rowId = (int) $object->id;
                        }
                        if (method_exists($this->db, 'free')) {
                            $this->db->free($selectRes);
                        }
                    }
                }

                if ($rowId > 0) {
                    $operations['insert']['message'] = 'ID '.$rowId;
                } else {
                    $operations['insert']['message'] = 'missing_identifier';
                    $operations['insert']['success'] = false;
                }
            }

            if (!$operations['insert']['success']) {
                return $result;
            }

            $updateSql = 'UPDATE '.$table.' SET ';
            $updateSql .= $fields['message']."='".$this->db->escape($this->truncate($updatedMessage, 1024))."', ";
            $updateSql .= $fields['success'].'=0 WHERE '.$fields['rowid'].'='.(int) $rowId;
            $updateRes = $this->db->query($updateSql);
            if ($updateRes === false) {
                $operations['update']['message'] = $this->db->lasterror();
            } else {
                $operations['update']['success'] = true;
                $operations['update']['message'] = 'ID '.$rowId;
            }

            if (!$operations['update']['success']) {
                return $result;
            }

            $deleteSql = 'DELETE FROM '.$table.' WHERE '.$fields['rowid'].'='.(int) $rowId;
            $deleteRes = $this->db->query($deleteSql);
            if ($deleteRes === false) {
                $operations['delete']['message'] = $this->db->lasterror();
            } else {
                $operations['delete']['success'] = true;
                $operations['delete']['message'] = 'ID '.$rowId;
            }
        } catch (Throwable $exception) {
            $result['error'] = 'exception';
            $result['error_details'] = $exception->getMessage();
            dol_syslog(__METHOD__.' write test exception: '.$exception->getMessage(), LOG_ERR);
        } finally {
            if ($transactionOpened && method_exists($this->db, 'rollback')) {
                $this->db->rollback();
            } elseif (!$transactionOpened && $rowId !== null && $rowId > 0 && !$operations['delete']['success']) {
                $cleanupSql = 'DELETE FROM '.$table.' WHERE '.$fields['rowid'].'='.(int) $rowId;
                $this->db->query($cleanupSql);
            } elseif (!$transactionOpened && !$operations['insert']['success']) {
                $cleanupSql = 'DELETE FROM '.$table." WHERE ".$fields['message']."='".$this->db->escape($this->truncate($initialMessage, 1024))."'";
                $this->db->query($cleanupSql);
            }
        }

        if ($operations['insert']['success'] && $operations['update']['success'] && $operations['delete']['success']) {
            $result['success'] = true;
        }

        if (!$result['success'] && $result['error'] === '' && !$operations['insert']['success']) {
            $result['error'] = 'insert_failed';
        } elseif (!$result['success'] && $result['error'] === '' && !$operations['update']['success']) {
            $result['error'] = 'update_failed';
        } elseif (!$result['success'] && $result['error'] === '' && !$operations['delete']['success']) {
            $result['error'] = 'delete_failed';
        }

        return $result;
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

    /**
     * Retrieve the fully qualified table name.
     *
     * @return string
     */
    private function getLogTableName()
    {
        return $this->maintenanceService->getLogTableName();
    }

    /**
     * Describe the Brevo log table structure.
     *
     * @return array{exists:bool,fields:array<string,string>}
     */
    private function describeLogTable()
    {
        if ($this->logTableSchema !== null) {
            return $this->logTableSchema;
        }

        $schema = $this->maintenanceService->getLogTableSchema();
        if (!empty($schema['fields'])) {
            $schema['fields'] = $this->normalizeFieldMap($schema['fields']);
        } else {
            $schema['fields'] = array();
        }

        $this->logTableSchema = $schema;

        return $schema;
    }

    /**
     * List mandatory log columns.
     *
     * @return array<int,string>
     */
    private function getRequiredLogColumns()
    {
        return array('rowid', 'entity', 'date_event', 'method', 'endpoint', 'http_code', 'duration_ms', 'success', 'message');
    }

    /**
     * Normalise a list or map of column names to a canonical=>actual mapping.
     *
     * @param array<int|string,string> $columns
     * @return array<string,string>
     */
    private function normalizeFieldMap($columns)
    {
        $mapping = array();
        foreach ($columns as $key => $value) {
            if (is_string($key)) {
                $canonical = strtolower((string) $key);
                $mapping[$canonical] = (string) $value;
            } else {
                $canonical = strtolower((string) $value);
                $mapping[$canonical] = (string) $value;
            }
        }

        return $mapping;
    }

    /**
     * Format a timestamp for SQL comparisons.
     *
     * @param int $timestamp
     * @return string|null
     */
    private function formatDateForSql($timestamp)
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return null;
        }

        if (method_exists($this->db, 'idate')) {
            return $this->db->idate($timestamp);
        }

        return "'".date('Y-m-d H:i:s', $timestamp)."'";
    }

    /**
     * Build the SELECT clause mapping for the log retrieval query.
     *
     * @param array<string,string> $fields
     * @return array<string,string>
     */
    private function buildSelectMap(array $fields)
    {
        $mapping = array(
            'rowid' => 'id',
            'date_event' => 'date_event',
            'method' => 'method',
            'endpoint' => 'endpoint',
            'http_code' => 'http_code',
            'duration_ms' => 'duration_ms',
            'success' => 'success',
            'message' => 'message'
        );

        $selectMap = array();
        foreach ($mapping as $field => $alias) {
            if (isset($fields[$field])) {
                $selectMap[$alias] = $fields[$field];
            }
        }

        return $selectMap;
    }
}
