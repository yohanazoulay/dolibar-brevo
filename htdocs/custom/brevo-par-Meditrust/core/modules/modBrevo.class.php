<?php
declare(strict_types=1);

/**
 * @package   brevo-par-Meditrust
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Brevo module descriptor for Dolibarr 21.0.2
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modBrevo
 */
class modBrevo extends DolibarrModules
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->numero = 104601; // Unique module ID
        $this->rights_class = 'brevo';
        $this->family = 'crm';
        $this->module_position = '50';
        $this->editor_name = 'Meditrust';
        $this->editor_url = 'https://www.meditrust.fr';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'Brevo integration for Dolibarr';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->special = 0;
        $this->picto = 'brevo@brevo-par-Meditrust';

        $this->dirs = array();

        $this->config_page_url = array('setup.php@brevo-par-Meditrust');
        $this->langfiles = array('brevo@brevo-par-Meditrust');

        $this->module_parts = array(
            'hooks' => array('thirdpartycard', 'contactcard')
        );

        $this->const = array();

        $this->rights = array();
        $r = 0;
        $this->rights[$r][0] = 104601;
        $this->rights[$r][1] = 'Read Brevo lists';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'read';
        $r++;
        $this->rights[$r][0] = 104602;
        $this->rights[$r][1] = 'Synchronize contacts with Brevo';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'write';
        $r++;
        $this->rights[$r][0] = 104603;
        $this->rights[$r][1] = 'Remove contacts from Brevo';
        $this->rights[$r][2] = 'd';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'delete';

        $this->menu = array();

        $this->menu[] = array(
            'fk_menu' => 0,
            'type' => 'top',
            'titre' => 'Brevo',
            'mainmenu' => 'brevo',
            'leftmenu' => 'brevo_lists',
            'url' => '/brevo-par-Meditrust/brevo/lists.php',
            'langs' => 'brevo@brevo-par-Meditrust',
            'position' => 100,
            'enabled' => '\$conf->brevo->enabled && \$user->rights->brevo->read',
            'perms' => '\$user->rights->brevo->read',
            'target' => '',
            'user' => 2
        );

        $this->menu[] = array(
            'fk_menu' => 'fk_mainmenu=brevo',
            'type' => 'left',
            'titre' => 'BrevoLists',
            'mainmenu' => 'brevo',
            'leftmenu' => 'brevo_lists',
            'url' => '/brevo-par-Meditrust/brevo/lists.php',
            'langs' => 'brevo@brevo-par-Meditrust',
            'position' => 101,
            'enabled' => '\$conf->brevo->enabled && \$user->rights->brevo->read',
            'perms' => '\$user->rights->brevo->read',
            'target' => '',
            'user' => 2
        );
    }

    /**
     * Initialize module
     *
     * @param string $options Options
     * @return int
     */
    public function init($options = '')
    {
        $result = $this->_load_tables('/brevo-par-Meditrust/sql/');
        if ($result < 0) {
            return $result;
        }

        $sql = array();

        return $this->_init($sql, $options);
    }

    /**
     * Remove module
     *
     * @param string $options Options
     * @return int
     */
    public function remove($options = '')
    {
        $sql = array();

        return $this->_remove($sql, $options);
    }
}
