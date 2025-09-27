<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     DAO handling Brevo API log entries.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class BrevoLog
 */
class BrevoLog extends CommonObject
{
    /** @var string */
    public $element = 'brevo_log';

    /** @var string */
    public $table_element = 'brevo_log';

    /** @var int */
    public $id = 0;

    /** @var int */
    public $entity = 1;

    /** @var int */
    public $date_event = 0;

    /** @var string */
    public $method = '';

    /** @var string */
    public $endpoint = '';

    /** @var int */
    public $http_code = 0;

    /** @var int */
    public $duration_ms = 0;

    /** @var int */
    public $success = 0;

    /** @var string */
    public $message = '';

    /**
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create object into database.
     *
     * @param User|null $user User performing the action
     * @return int <0 if KO, id of created object if OK
     */
    public function create(User $user = null)
    {
        $this->error = '';
        $this->errors = array();

        $now = $this->date_event > 0 ? $this->date_event : dol_now();

        $sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element.' (';
        $sql .= 'entity, date_event, method, endpoint, http_code, duration_ms, success, message';
        $sql .= ') VALUES (';
        $sql .= (int) $this->entity.',';
        $sql .= $this->db->idate($now).',';
        $sql .= "'".$this->db->escape($this->method)."',";
        $sql .= "'".$this->db->escape($this->endpoint)."',";
        $sql .= (int) $this->http_code.',';
        $sql .= (int) $this->duration_ms.',';
        $sql .= (int) $this->success.',';
        if ($this->message !== '') {
            $sql .= "'".$this->db->escape($this->message)."'";
        } else {
            $sql .= 'NULL';
        }
        $sql .= ')';

        $result = $this->db->query($sql);
        if ($result === false) {
            $this->error = $this->db->lasterror();

            return -1;
        }

        $this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);

        return $this->id;
    }
}
