<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     DAO to persist Brevo synchronisation entries.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/brevointegration/lib/brevointegration_date.lib.php');

/**
 * Class BrevoSync
 */
class BrevoSync extends CommonObject
{
    /** @var string */
    public $element = 'brevo_contactsync';

    /** @var string */
    public $table_element = 'brevo_contactsync';

    /** @var int */
    public $id;

    /** @var int */
    public $entity = 1;

    /** @var int */
    public $fk_socpeople = 0;

    /** @var int */
    public $fk_societe = 0;

    /** @var int */
    public $brevo_list_id = 0;

    /** @var string */
    public $brevo_list_label = '';

    /** @var string */
    public $brevo_contact_id = '';

    /** @var string */
    public $date_sync = '';

    /** @var string */
    public $status = 'ok';

    /**
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->ismultientitymanaged = 1;
    }

    /**
     * Create synchronisation record
     *
     * @param User $user Current user
     * @return int
     */
    public function create(User $user)
    {
        $now = dol_now();
        $this->date_sync = $now;

        global $conf;
        $entity = isset($conf->entity) ? (int) $conf->entity : 1;

        $this->db->begin();

        $sqlSelect = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$this->table_element;
        $sqlSelect .= ' WHERE entity='.$entity;
        $sqlSelect .= ' AND fk_socpeople='.(int) $this->fk_socpeople;
        $sqlSelect .= ' AND fk_societe='.(int) $this->fk_societe;
        $sqlSelect .= ' AND brevo_list_id='.(int) $this->brevo_list_id;

        $resSelect = $this->db->query($sqlSelect);
        if (!$resSelect) {
            $this->db->rollback();
            $this->error = $this->db->lasterror();
            return -1;
        }

        $obj = $this->db->fetch_object($resSelect);
        if ($obj) {
            $update = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element;
            $update .= " SET status='".$this->db->escape($this->status)."',";
            $update .= " brevo_contact_id='".$this->db->escape($this->brevo_contact_id)."'";
            if ($this->brevo_list_label !== '') {
                $update .= ", brevo_list_label='".$this->db->escape($this->brevo_list_label)."'";
            }
            $dateSql = brevointegration_format_sql_datetime($this->db, $now);
            $update .= ', date_sync='.($dateSql !== null ? $dateSql : 'NULL');
            $update .= ' WHERE rowid='.(int) $obj->rowid;

            $res = $this->db->query($update);
            if (!$res) {
                $this->db->rollback();
                $this->error = $this->db->lasterror();
                return -1;
            }

            $this->id = (int) $obj->rowid;
        } else {
            $sql = 'INSERT INTO '.MAIN_DB_PREFIX.$this->table_element.' (entity, fk_socpeople, fk_societe, brevo_list_id, brevo_list_label, brevo_contact_id, date_sync, status) VALUES (';
            $sql .= $entity.',';
            $sql .= (int) $this->fk_socpeople.',';
            $sql .= (int) $this->fk_societe.',';
            $sql .= (int) $this->brevo_list_id.',';
            $sql .= "'".$this->db->escape($this->brevo_list_label)."',";
            $sql .= "'".$this->db->escape($this->brevo_contact_id)."',";
            $dateSql = brevointegration_format_sql_datetime($this->db, $now);
            $sql .= ($dateSql !== null ? $dateSql : 'NULL').',';
            $sql .= "'".$this->db->escape($this->status)."')";

            $res = $this->db->query($sql);
            if (!$res) {
                $this->db->rollback();
                $this->error = $this->db->lasterror();
                return -1;
            }

            $this->id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
        }

        $this->db->commit();

        return $this->id;
    }

    /**
     * Mark synchronisation line as removed
     *
     * @param int $contactId    Contact ID
     * @param int $thirdpartyId Third party ID
     * @param int $listId       Brevo list identifier
     * @return int
     */
    public function markRemoved($contactId, $thirdpartyId, $listId)
    {
        $sql = 'UPDATE '.MAIN_DB_PREFIX.$this->table_element;
        $sql .= " SET status='removed'";
        $dateSql = brevointegration_format_sql_datetime($this->db, dol_now());
        $sql .= ', date_sync='.($dateSql !== null ? $dateSql : 'NULL');
        global $conf;
        $entity = isset($conf->entity) ? (int) $conf->entity : 1;

        $sql .= ' WHERE entity='.$entity;
        $sql .= ' AND fk_socpeople='.(int) $contactId;
        $sql .= ' AND brevo_list_id='.(int) $listId;
        if ($thirdpartyId > 0) {
            $sql .= ' AND fk_societe='.(int) $thirdpartyId;
        }

        $this->db->begin();
        $res = $this->db->query($sql);
        if (!$res) {
            $this->db->rollback();
            $this->error = $this->db->lasterror();
            return -1;
        }
        $this->db->commit();

        return 1;
    }

    /**
     * Fetch synchronisation entries for a contact
     *
     * @param int $contactId    Contact ID
     * @param int $thirdpartyId Third party ID
     * @return array
     */
    public function fetchByContact($contactId, $thirdpartyId = 0)
    {
        global $conf;
        $entity = isset($conf->entity) ? (int) $conf->entity : 1;

        $sql = 'SELECT rowid, fk_socpeople, fk_societe, brevo_list_id, brevo_list_label, brevo_contact_id, date_sync, status';
        $sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element;
        $sql .= ' WHERE entity='.$entity;
        $sql .= ' AND fk_socpeople='.(int) $contactId;
        if ($thirdpartyId > 0) {
            $sql .= ' AND fk_societe='.(int) $thirdpartyId;
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return array();
        }

        $entries = array();
        while ($obj = $this->db->fetch_object($resql)) {
            $entries[] = array(
                'rowid' => (int) $obj->rowid,
                'fk_socpeople' => (int) $obj->fk_socpeople,
                'fk_societe' => (int) $obj->fk_societe,
                'brevo_list_id' => (int) $obj->brevo_list_id,
                'brevo_list_label' => isset($obj->brevo_list_label) ? $obj->brevo_list_label : '',
                'brevo_contact_id' => $obj->brevo_contact_id,
                'date_sync' => $this->db->jdate($obj->date_sync),
                'status' => $obj->status,
            );
        }
        $this->db->free($resql);

        return $entries;
    }
}
