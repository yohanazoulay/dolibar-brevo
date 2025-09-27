<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Brevo Integration module descriptor for Dolibarr 21.0.2
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modBrevoIntegration
 */
class modBrevoIntegration extends DolibarrModules
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
        $this->rights_class = 'brevointegration';
        $this->family = 'crm';
        $this->module_position = '50';
        $this->editor_name = 'Meditrust';
        $this->editor_url = 'https://www.meditrust.fr';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'Brevo integration for Dolibarr';
        $this->version = '1.3.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->special = 0;
        $this->picto = 'icon-picto-brevo.svg@brevointegration';

        $this->dirs = array();

        $this->config_page_url = array(
            'setup.php@brevointegration',
            '../custom/brevointegration/admin/logs.php' // direct path so SaaS hosting resolves the custom admin page correctly
        );
        $this->langfiles = array('brevointegration@brevointegration');

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
            'titre' => 'BrevoIntegration',
            'mainmenu' => 'brevointegration',
            'leftmenu' => 'brevointegration_lists',
            'url' => '/brevointegration/brevointegration/lists.php',
            'langs' => 'brevointegration@brevointegration',
            'picto' => 'icon-picto-brevo.svg@brevointegration',
            'position' => 100,
            'enabled' => '\$conf->brevointegration->enabled && \$user->rights->brevointegration->read',
            'perms' => '\$user->rights->brevointegration->read',
            'target' => '',
            'user' => 2
        );

        $this->menu[] = array(
            'fk_menu' => 'fk_mainmenu=brevointegration',
            'type' => 'left',
            'titre' => 'BrevoIntegrationLists',
            'mainmenu' => 'brevointegration',
            'leftmenu' => 'brevointegration_lists',
            'url' => '/brevointegration/brevointegration/lists.php',
            'langs' => 'brevointegration@brevointegration',
            'picto' => 'icon-picto-brevo.svg@brevointegration',
            'position' => 101,
            'enabled' => '\$conf->brevointegration->enabled && \$user->rights->brevointegration->read',
            'perms' => '\$user->rights->brevointegration->read',
            'target' => '',
            'user' => 2
        );

        $this->menu[] = array(
            'fk_menu' => 'fk_mainmenu=brevointegration',
            'type' => 'left',
            'titre' => 'BrevoIntegrationLogs',
            'mainmenu' => 'brevointegration',
            'leftmenu' => 'brevointegration_logs',
            'url' => '/brevointegration/admin/logs.php',
            'langs' => 'brevointegration@brevointegration',
            'picto' => 'icon-picto-brevo.svg@brevointegration',
            'position' => 102,
            'enabled' => '\$conf->brevointegration->enabled && \$user->admin',
            'perms' => '\$user->admin',
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
        $result = $this->_load_tables('/brevointegration/sql/');
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
