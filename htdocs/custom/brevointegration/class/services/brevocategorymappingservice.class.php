<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Service to manage mappings between Dolibarr contact categories and Brevo lists.
 */

class BrevoCategoryMappingService
{
    /** @var DoliDB */
    private $db;

    /** @var Conf */
    private $conf;

    /** @var string */
    public const CONST_NAME = 'MAIN_BREVOINTEGRATION_CATEGORY_MAPPING';

    /**
     * @param DoliDB $db   Database handler
     * @param Conf   $conf Dolibarr configuration
     */
    public function __construct($db, $conf)
    {
        $this->db = $db;
        $this->conf = $conf;
    }

    /**
     * Retrieve persisted mappings between Dolibarr categories and Brevo lists.
     *
     * @return array<int,array<string,int>>
     */
    public function getMappings()
    {
        $rawValue = isset($this->conf->global->{self::CONST_NAME}) ? $this->conf->global->{self::CONST_NAME} : '';
        if (!is_string($rawValue) || $rawValue === '') {
            return array();
        }

        $decoded = json_decode($rawValue, true);
        if (!is_array($decoded)) {
            return array();
        }

        return $this->sanitizeEntries($decoded);
    }

    /**
     * Persist mapping entries.
     *
     * @param array<int,array<string,int>> $entries
     * @return bool
     */
    public function saveMappings(array $entries)
    {
        $cleanEntries = $this->sanitizeEntries($entries);
        $encoded = json_encode($cleanEntries);
        if ($encoded === false) {
            return false;
        }

        $result = dolibarr_set_const(
            $this->db,
            self::CONST_NAME,
            $encoded,
            'chaine',
            0,
            '',
            $this->getEntity()
        );

        if ($result > 0) {
            if (!isset($this->conf->global)) {
                $this->conf->global = new stdClass();
            }
            $this->conf->global->{self::CONST_NAME} = $encoded;
        }

        return $result > 0;
    }

    /**
     * Return Brevo list IDs linked to the provided category identifiers.
     *
     * @param array<int,int> $categoryIds
     * @return array<int,int>
     */
    public function getListIdsForCategories(array $categoryIds)
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        if (empty($categoryIds)) {
            return array();
        }

        $listIds = array();
        foreach ($this->getMappings() as $entry) {
            if (!isset($entry['category_id'], $entry['list_id'])) {
                continue;
            }
            if (in_array((int) $entry['category_id'], $categoryIds, true)) {
                $listIds[] = (int) $entry['list_id'];
            }
        }

        return array_values(array_unique($listIds));
    }

    /**
     * Fetch category identifiers assigned to a Dolibarr contact.
     *
     * @param int $contactId Contact rowid
     * @return array<int,int>
     */
    public function getContactCategoryIds($contactId)
    {
        $contactId = (int) $contactId;
        if ($contactId <= 0) {
            return array();
        }

        $entity = $this->getEntity();
        $sql = 'SELECT cc.fk_categorie AS category_id';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'categorie_contact AS cc';
        $sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'categorie AS c ON c.rowid = cc.fk_categorie';
        $sql .= ' WHERE cc.fk_socpeople='.(int) $contactId;
        $sql .= ' AND c.entity IN (0, '.$entity.')';
        $sql .= ' ORDER BY c.label ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            return array();
        }

        $categoryIds = array();
        while ($obj = $this->db->fetch_object($resql)) {
            if (!isset($obj->category_id)) {
                continue;
            }
            $categoryIds[] = (int) $obj->category_id;
        }

        if (method_exists($this->db, 'free')) {
            $this->db->free($resql);
        }

        return array_values(array_unique($categoryIds));
    }

    /**
     * Retrieve mappings that concern the provided contact (category label included).
     *
     * @param int $contactId Contact rowid
     * @return array<int,array<string,mixed>>
     */
    public function getMappingsForContact($contactId)
    {
        $categoryIds = $this->getContactCategoryIds($contactId);
        if (empty($categoryIds)) {
            return array();
        }

        $labels = $this->getCategoryLabels($categoryIds);
        $results = array();
        foreach ($this->getMappings() as $entry) {
            if (!isset($entry['category_id'], $entry['list_id'])) {
                continue;
            }
            $categoryId = (int) $entry['category_id'];
            if (!in_array($categoryId, $categoryIds, true)) {
                continue;
            }
            $results[] = array(
                'category_id' => $categoryId,
                'category_label' => isset($labels[$categoryId]) ? $labels[$categoryId] : '',
                'list_id' => (int) $entry['list_id'],
            );
        }

        return $results;
    }

    /**
     * Retrieve all Dolibarr contact categories available for mapping.
     *
     * @return array<int,string>
     */
    public function getAllContactCategories()
    {
        $entity = $this->getEntity();
        $type = $this->getContactCategoryType();

        $sql = 'SELECT c.rowid, c.label';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'categorie AS c';
        $sql .= ' WHERE c.type='.(int) $type;
        $sql .= ' AND c.entity IN (0, '.$entity.')';
        $sql .= ' ORDER BY c.label ASC';

        $resql = $this->db->query($sql);
        if (!$resql) {
            return array();
        }

        $categories = array();
        while ($obj = $this->db->fetch_object($resql)) {
            if (!isset($obj->rowid)) {
                continue;
            }
            $categories[(int) $obj->rowid] = isset($obj->label) ? (string) $obj->label : '';
        }

        if (method_exists($this->db, 'free')) {
            $this->db->free($resql);
        }

        return $categories;
    }

    /**
     * Retrieve labels for a set of category identifiers.
     *
     * @param array<int,int> $categoryIds
     * @return array<int,string>
     */
    public function getCategoryLabels(array $categoryIds)
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        if (empty($categoryIds)) {
            return array();
        }

        $sql = 'SELECT c.rowid, c.label';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'categorie AS c';
        $sql .= ' WHERE c.rowid IN ('.implode(',', $categoryIds).')';

        $resql = $this->db->query($sql);
        if (!$resql) {
            return array();
        }

        $labels = array();
        while ($obj = $this->db->fetch_object($resql)) {
            if (!isset($obj->rowid)) {
                continue;
            }
            $labels[(int) $obj->rowid] = isset($obj->label) ? (string) $obj->label : '';
        }

        if (method_exists($this->db, 'free')) {
            $this->db->free($resql);
        }

        return $labels;
    }

    /**
     * Ensure mapping entries contain valid identifiers.
     *
     * @param array<int,array<string,int>> $entries
     * @return array<int,array<string,int>>
     */
    private function sanitizeEntries(array $entries)
    {
        $clean = array();
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $categoryId = isset($entry['category_id']) ? (int) $entry['category_id'] : 0;
            $listId = isset($entry['list_id']) ? (int) $entry['list_id'] : 0;
            if ($categoryId <= 0 || $listId <= 0) {
                continue;
            }
            $clean[] = array(
                'category_id' => $categoryId,
                'list_id' => $listId,
            );
        }

        return $clean;
    }

    /**
     * @return int
     */
    private function getEntity()
    {
        return isset($this->conf->entity) ? (int) $this->conf->entity : 1;
    }

    /**
     * @return int
     */
    private function getContactCategoryType()
    {
        if (defined('Categorie::TYPE_CONTACT')) {
            /** @var int $type */
            $type = constant('Categorie::TYPE_CONTACT');

            return (int) $type;
        }

        return 4;
    }
}
