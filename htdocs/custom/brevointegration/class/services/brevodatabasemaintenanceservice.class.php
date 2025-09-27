<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Helper service to inspect and repair Brevo database tables.
 */

/**
 * Class BrevoDatabaseMaintenanceService
 */
class BrevoDatabaseMaintenanceService
{
    /** @var DoliDB */
    private $db;

    /**
     * @var array<string,array<string,mixed>>
     */
    private $blueprints;

    /**
     * @var array<string,array{exists:bool,fields:array<string,string>}> 
     */
    private $schemaCache = array();

    /**
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->blueprints = $this->buildBlueprints();
    }

    /**
     * Retrieve the current schema of the Brevo log table.
     *
     * @return array{exists:bool,fields:array<string,string>}
     */
    public function getLogTableSchema()
    {
        return $this->getTableSchema('log');
    }

    /**
     * Retrieve the current schema of the Brevo contact synchronisation table.
     *
     * @return array{exists:bool,fields:array<string,string>}
     */
    public function getContactSyncTableSchema()
    {
        return $this->getTableSchema('contactsync');
    }

    /**
     * Expose status information for the Brevo log table.
     *
     * @return array{table_name:string,exists:bool,ready:bool,missing_columns:array<int,string>,available_columns:array<int,string>}
     */
    public function getLogTableStatus()
    {
        return $this->getTableStatus('log');
    }

    /**
     * Expose status information for the Brevo contact synchronisation table.
     *
     * @return array{table_name:string,exists:bool,ready:bool,missing_columns:array<int,string>,available_columns:array<int,string>}
     */
    public function getContactSyncTableStatus()
    {
        return $this->getTableStatus('contactsync');
    }

    /**
     * Compute SQL statements required to repair both Brevo tables.
     *
     * @param array<string,array{table_name:string,exists:bool,ready:bool,missing_columns:array<int,string>,available_columns:array<int,string>}> $statuses
     * @return array<int,string>
     */
    public function buildPatch(array $statuses)
    {
        $statements = array();
        foreach ($statuses as $key => $status) {
            $statements = array_merge($statements, $this->buildPatchForTable((string) $key, $status));
        }

        if (empty($statements)) {
            return array();
        }

        $header = '-- BrevoIntegration schema patch generated on '.date('c');

        return array_merge(array($header), $statements);
    }

    /**
     * Compute SQL statements required to repair the Brevo log table.
     *
     * @param array{table_name:string,exists:bool,ready:bool,missing_columns:array<int,string>,available_columns:array<int,string>}|null $status
     * @return array<int,string>
     */
    public function buildLogTablePatch($status = null)
    {
        if ($status === null) {
            $status = $this->getLogTableStatus();
        }

        return $this->buildPatchForTable('log', $status);
    }

    /**
     * Compute SQL statements required to repair the Brevo contact synchronisation table.
     *
     * @param array{table_name:string,exists:bool,ready:bool,missing_columns:array<int,string>,available_columns:array<int,string>}|null $status
     * @return array<int,string>
     */
    public function buildContactSyncTablePatch($status = null)
    {
        if ($status === null) {
            $status = $this->getContactSyncTableStatus();
        }

        return $this->buildPatchForTable('contactsync', $status);
    }

    /**
     * Return the fully qualified name of the Brevo log table.
     *
     * @return string
     */
    public function getLogTableName()
    {
        return $this->getTableName('log');
    }

    /**
     * Return the fully qualified name of the Brevo contact synchronisation table.
     *
     * @return string
     */
    public function getContactSyncTableName()
    {
        return $this->getTableName('contactsync');
    }

    /**
     * Inspect a table schema using the database driver.
     *
     * @param string $key Table identifier (log|contactsync)
     * @return array{exists:bool,fields:array<string,string>}
     */
    private function getTableSchema($key)
    {
        if (isset($this->schemaCache[$key])) {
            return $this->schemaCache[$key];
        }

        $schema = array('exists' => false, 'fields' => array());
        $blueprint = $this->getBlueprint($key);
        $tableName = $this->getTableName($key);

        if (!is_object($this->db)) {
            $this->schemaCache[$key] = $schema;

            return $schema;
        }

        if (method_exists($this->db, 'DDLDescTable')) {
            $info = $this->db->DDLDescTable($tableName, '', '', true);
            if (is_array($info) && isset($info['fields']) && is_array($info['fields']) && !empty($info['fields'])) {
                $schema['exists'] = true;
                foreach ($info['fields'] as $fieldName => $definition) {
                    $canonical = strtolower((string) $fieldName);
                    $schema['fields'][$canonical] = (string) $fieldName;
                }

                $this->schemaCache[$key] = $schema;

                return $schema;
            }
        }

        if (method_exists($this->db, 'table_exists') && $this->db->table_exists($tableName)) {
            $schema['exists'] = true;
            foreach (array_keys($blueprint['columns']) as $column) {
                $schema['fields'][$column] = $column;
            }

            $this->schemaCache[$key] = $schema;

            return $schema;
        }

        if (method_exists($this->db, 'query')) {
            $sql = 'SELECT 1 FROM '.$tableName.' WHERE 1=0';
            $resql = $this->db->query($sql);
            if ($resql !== false) {
                $schema['exists'] = true;
                foreach (array_keys($blueprint['columns']) as $column) {
                    $schema['fields'][$column] = $column;
                }
                if (method_exists($this->db, 'free')) {
                    $this->db->free($resql);
                }
            }
        }

        $this->schemaCache[$key] = $schema;

        return $schema;
    }

    /**
     * Compute status details for a table.
     *
     * @param string $key Table identifier
     * @return array{table_name:string,exists:bool,ready:bool,missing_columns:array<int,string>,available_columns:array<int,string>}
     */
    private function getTableStatus($key)
    {
        $blueprint = $this->getBlueprint($key);
        $schema = $this->getTableSchema($key);
        $missing = array();
        foreach (array_keys($blueprint['columns']) as $column) {
            if (!isset($schema['fields'][$column])) {
                $missing[] = $column;
            }
        }

        return array(
            'table_name' => $this->getTableName($key),
            'exists' => $schema['exists'],
            'ready' => $schema['exists'] && empty($missing),
            'missing_columns' => $missing,
            'available_columns' => array_values($schema['fields'])
        );
    }

    /**
     * Build SQL statements for a specific table.
     *
     * @param string                                                                                                  $key
     * @param array{table_name:string,exists:bool,ready:bool,missing_columns:array<int,string>,available_columns:array<int,string>} $status
     * @return array<int,string>
     */
    private function buildPatchForTable($key, array $status)
    {
        $blueprint = $this->getBlueprint($key);
        $tableName = $this->getTableName($key);
        $statements = array();

        if (!$status['exists']) {
            $lines = array();
            foreach ($blueprint['columns'] as $column => $definition) {
                $lines[] = $column.' '.$definition;
            }
            foreach ($blueprint['constraints'] as $constraint) {
                $lines[] = $constraint;
            }

            $engine = strtoupper((string) $blueprint['engine']);
            $createSql = 'CREATE TABLE '.$tableName;
            if (!empty($lines)) {
                $createSql .= " (\n    ".implode(",\n    ", $lines)."\n)";
            }
            $createSql .= ' ENGINE='.$engine.';';
            $statements[] = $createSql;

            foreach ($blueprint['indexes'] as $indexSql) {
                $statements[] = rtrim(sprintf($indexSql, $tableName), ';').';';
            }

            return $statements;
        }

        foreach ($status['missing_columns'] as $column) {
            if (!isset($blueprint['columns'][$column])) {
                continue;
            }

            $statements[] = 'ALTER TABLE '.$tableName.' ADD COLUMN '.$column.' '.$blueprint['columns'][$column].';';
        }

        return $statements;
    }

    /**
     * Resolve the fully qualified table name.
     *
     * @param string $key Table identifier
     * @return string
     */
    private function getTableName($key)
    {
        $blueprint = $this->getBlueprint($key);
        $shortName = isset($blueprint['table']) ? (string) $blueprint['table'] : '';
        $prefix = defined('MAIN_DB_PREFIX') ? MAIN_DB_PREFIX : 'llx_';

        return $prefix.$shortName;
    }

    /**
     * Fetch table blueprint definition.
     *
     * @param string $key
     * @return array<string,mixed>
     */
    private function getBlueprint($key)
    {
        if (isset($this->blueprints[$key])) {
            return $this->blueprints[$key];
        }

        return array(
            'table' => '',
            'columns' => array(),
            'constraints' => array(),
            'indexes' => array(),
            'engine' => 'innodb',
        );
    }

    /**
     * Define expected schemas for the Brevo tables.
     *
     * @return array<string,array<string,mixed>>
     */
    private function buildBlueprints()
    {
        return array(
            'log' => array(
                'table' => 'brevo_log',
                'engine' => 'innodb',
                'columns' => array(
                    'rowid' => 'INT AUTO_INCREMENT PRIMARY KEY',
                    'entity' => 'INT NOT NULL DEFAULT 1',
                    'date_event' => 'DATETIME NOT NULL',
                    'method' => 'VARCHAR(8) NOT NULL',
                    'endpoint' => 'VARCHAR(255) NOT NULL',
                    'http_code' => 'INT NOT NULL DEFAULT 0',
                    'duration_ms' => 'INT NOT NULL DEFAULT 0',
                    'success' => 'TINYINT(1) NOT NULL DEFAULT 0',
                    'message' => 'TEXT NULL'
                ),
                'constraints' => array(),
                'indexes' => array(
                    'CREATE INDEX idx_brevo_log_entity_date ON %s (entity, date_event)',
                    'CREATE INDEX idx_brevo_log_success ON %s (success)'
                ),
            ),
            'contactsync' => array(
                'table' => 'brevo_contactsync',
                'engine' => 'innodb',
                'columns' => array(
                    'rowid' => 'INT AUTO_INCREMENT PRIMARY KEY',
                    'entity' => 'INT NOT NULL DEFAULT 1',
                    'fk_socpeople' => 'INT NOT NULL DEFAULT 0',
                    'fk_societe' => 'INT NOT NULL DEFAULT 0',
                    'brevo_list_id' => 'INT NOT NULL',
                    'brevo_list_label' => "VARCHAR(255) NOT NULL DEFAULT ''",
                    'brevo_contact_id' => 'VARCHAR(128) NOT NULL',
                    'date_sync' => 'DATETIME NOT NULL',
                    'status' => "VARCHAR(16) NOT NULL DEFAULT 'ok'"
                ),
                'constraints' => array(
                    'CONSTRAINT idx_brevo_contactsync_contact_list UNIQUE (entity, fk_socpeople, fk_societe, brevo_list_id)'
                ),
                'indexes' => array(
                    'CREATE INDEX idx_brevo_contactsync_socpeople ON %s (fk_socpeople)',
                    'CREATE INDEX idx_brevo_contactsync_societe ON %s (fk_societe)',
                    'CREATE INDEX idx_brevo_contactsync_status ON %s (status)'
                ),
            ),
        );
    }
}
