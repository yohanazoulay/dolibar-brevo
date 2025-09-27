<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Hooks to integrate Brevo actions inside Dolibarr cards.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
dol_include_once('/brevointegration/class/brevoapi.class.php');
dol_include_once('/brevointegration/class/brevosync.class.php');
dol_include_once('/brevointegration/class/services/brevofieldmappingservice.class.php');

/**
 * Class ActionsBrevointegration
 */
class ActionsBrevointegration
{
    /** @var DoliDB */
    public $db;

    /**
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Handle POST actions
     *
     * @param array        $parameters Hook parameters
     * @param CommonObject $object     Current object
     * @param string       $action     Current action
     * @param HookManager  $hookmanager Hook manager
     * @return int
     */
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $conf, $user;

        if (empty($conf->brevointegration->enabled)) {
            return 0;
        }

        $context = isset($parameters['context']) ? explode(':', $parameters['context']) : array();
        if (!in_array('contactcard', $context) && !in_array('thirdpartycard', $context)) {
            return 0;
        }

        $langs->load('brevointegration@brevointegration');

        $brevoAction = GETPOST('brevo_action', 'alpha');
        if ($brevoAction === '') {
            return 0;
        }

        if (!checkToken()) {
            setEventMessages($langs->trans('ErrorBadToken'), null, 'errors');
            return -1;
        }

        $apiKey = !empty($conf->global->MAIN_BREVOINTEGRATION_APIKEY) ? $conf->global->MAIN_BREVOINTEGRATION_APIKEY : '';
        if ($apiKey === '') {
            setEventMessages($langs->trans('BrevoMissingApiKey'), null, 'errors');
            return -1;
        }

        $api = new BrevoApi($this->db, $conf, $apiKey);
        $sync = new BrevoSync($this->db);

        $contactId = $this->getContactId($object, $context);
        $thirdpartyId = $this->getThirdpartyId($object, $context);
        $email = $this->getEmail($object, $context);

        if ($email === '') {
            setEventMessages($langs->trans('BrevoMissingEmail'), null, 'errors');
            return -1;
        }

        if ($brevoAction === 'push') {
            if (empty($user->rights->brevointegration->write)) {
                setEventMessages($langs->trans('NotEnoughPermissions'), null, 'errors');
                return -1;
            }

            $listId = GETPOST('brevo_list_id', 'int');
            if ($listId <= 0) {
                setEventMessages($langs->trans('BrevoSelectList'), null, 'errors');
                return -1;
            }

            $attributes = $this->buildContactAttributes($object, $context);
            $response = $api->upsertContact($email, $attributes, array($listId));
            if (empty($response['success'])) {
                setEventMessages($response['error'], null, 'errors');
                return -1;
            }

            $sync->fk_socpeople = $contactId;
            $sync->fk_societe = $thirdpartyId;
            $sync->brevo_list_id = $listId;
            $sync->brevo_contact_id = isset($response['data']['id']) ? (string) $response['data']['id'] : $email;
            $sync->status = 'ok';

            $res = $sync->create($user);
            if ($res < 0) {
                setEventMessages($sync->error, null, 'errors');
                return -1;
            }

            setEventMessages($langs->trans('BrevoSyncSuccess'), null, 'mesgs');
        } elseif ($brevoAction === 'remove') {
            if (empty($user->rights->brevointegration->delete)) {
                setEventMessages($langs->trans('NotEnoughPermissions'), null, 'errors');
                return -1;
            }

            $listId = GETPOST('brevo_list_id', 'int');
            if ($listId <= 0) {
                setEventMessages($langs->trans('BrevoSelectList'), null, 'errors');
                return -1;
            }

            $response = $api->removeContactsFromList($listId, array($email));
            if (empty($response['success'])) {
                setEventMessages($response['error'], null, 'errors');
                return -1;
            }

            $res = $sync->markRemoved($contactId, $thirdpartyId, $listId);
            if ($res < 0) {
                setEventMessages($sync->error, null, 'errors');
                return -1;
            }

            setEventMessages($langs->trans('BrevoRemovalSuccess'), null, 'mesgs');
        }

        return 0;
    }

    /**
     * Print footer buttons
     *
     * @param array        $parameters Hook parameters
     * @param CommonObject $object     Current object
     * @param string       $action     Current action
     * @param HookManager  $hookmanager Hook manager
     * @return int
     */
    public function printCardFooter($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $conf, $user;

        if (empty($conf->brevointegration->enabled)) {
            return 0;
        }

        $context = isset($parameters['context']) ? explode(':', $parameters['context']) : array();
        if (!in_array('contactcard', $context) && !in_array('thirdpartycard', $context)) {
            return 0;
        }

        $langs->load('brevointegration@brevointegration');

        $lists = array();
        $listsError = '';
        if (!empty($conf->global->MAIN_BREVOINTEGRATION_APIKEY) && !empty($user->rights->brevointegration->read)) {
            $api = new BrevoApi($this->db, $conf, $conf->global->MAIN_BREVOINTEGRATION_APIKEY);
            $response = $api->getLists(50, 0);
            if (!empty($response['success']) && isset($response['data']['lists'])) {
                $lists = $response['data']['lists'];
            } elseif (!empty($response['error'])) {
                $listsError = $response['error'];
            }
        }

        $sync = new BrevoSync($this->db);
        $syncEntries = $sync->fetchByContact($this->getContactId($object, $context), $this->getThirdpartyId($object, $context));

        $form = new Form($this->db);
        $parameters = array(
            'object' => $object,
            'context' => $context,
            'lists' => $lists,
            'listsError' => $listsError,
            'syncEntries' => $syncEntries,
            'user' => $user,
            'form' => $form,
        );

        $hookmanager->resprints .= $this->renderTemplate('contact_brevointegration.tpl.php', $parameters);

        return 0;
    }

    /**
     * Render template
     *
     * @param string $template Template name
     * @param array  $data     Data
     * @return string
     */
    private function renderTemplate($template, array $data)
    {
        global $conf, $langs;

        $templatePath = dol_buildpath('/brevointegration/tpl/'.$template);
        if (!is_readable($templatePath)) {
            return '';
        }

        extract($data);
        ob_start();
        include $templatePath;

        return ob_get_clean();
    }

    /**
     * @param CommonObject $object  Current object
     * @param array        $context Context array
     * @return int
     */
    private function getContactId($object, array $context)
    {
        if (in_array('contactcard', $context) && isset($object->id)) {
            return (int) $object->id;
        }

        return 0;
    }

    /**
     * @param CommonObject $object  Current object
     * @param array        $context Context array
     * @return int
     */
    private function getThirdpartyId($object, array $context)
    {
        if (in_array('thirdpartycard', $context) && isset($object->id)) {
            return (int) $object->id;
        }

        if (in_array('contactcard', $context) && !empty($object->fk_soc)) {
            return (int) $object->fk_soc;
        }

        return 0;
    }

    /**
     * @param CommonObject $object  Current object
     * @param array        $context Context array
     * @return string
     */
    private function getEmail($object, array $context)
    {
        if (in_array('contactcard', $context) && !empty($object->email)) {
            return $object->email;
        }
        if (in_array('thirdpartycard', $context) && !empty($object->email)) {
            return $object->email;
        }

        return '';
    }

    /**
     * Build contact attributes for Brevo
     *
     * @param CommonObject $object  Current object
     * @param array        $context Context array
     * @return array
     */
    private function buildContactAttributes($object, array $context)
    {
        global $conf;

        $service = new BrevoFieldMappingService($this->db, $conf);
        $attributes = array();

        if (in_array('contactcard', $context)) {
            $attributes = $this->buildAttributesForType($object, 'contact', $service);
        } elseif (in_array('thirdpartycard', $context)) {
            $attributes = $this->buildAttributesForType($object, 'thirdparty', $service);
        }

        return $attributes;
    }

    /**
     * Build attribute array for a specific Dolibarr object type.
     *
     * @param CommonObject             $object  Current object
     * @param string                   $type    contact|thirdparty
     * @param BrevoFieldMappingService $service Mapping service
     * @return array<string,string>
     */
    private function buildAttributesForType($object, $type, BrevoFieldMappingService $service)
    {
        $attributes = array();
        $mapping = $service->getMappingForType($type);

        foreach ($mapping as $entry) {
            if (empty($entry['attribute']) || empty($entry['source']) || empty($entry['field'])) {
                continue;
            }

            $value = '';
            if ($entry['source'] === 'standard') {
                $value = $this->getStandardFieldValue($object, $entry['field']);
            } elseif ($entry['source'] === 'extrafield') {
                $value = $this->getExtrafieldValue($object, $entry['field']);
            }

            if (is_array($value)) {
                $attributes[$entry['attribute']] = implode(', ', $value);
            } else {
                $attributes[$entry['attribute']] = (string) $value;
            }
        }

        return $attributes;
    }

    /**
     * Retrieve standard field value from object.
     *
     * @param CommonObject $object Current object
     * @param string       $field  Property name
     * @return string
     */
    private function getStandardFieldValue($object, $field)
    {
        if (isset($object->{$field})) {
            return (string) $object->{$field};
        }

        return '';
    }

    /**
     * Retrieve extrafield value from object.
     *
     * @param CommonObject $object Current object
     * @param string       $field  Extrafield code
     * @return string|array<string>
     */
    private function getExtrafieldValue($object, $field)
    {
        $key = 'options_'.$field;

        if (!isset($object->array_options[$key])) {
            if (method_exists($object, 'fetch_optionals') && isset($object->id) && isset($object->table_element)) {
                $object->fetch_optionals($object->id, $object->table_element);
            }
        }

        if (isset($object->array_options[$key])) {
            $value = $object->array_options[$key];
            if (is_array($value)) {
                return $value;
            }

            return (string) $value;
        }

        return '';
    }
}
