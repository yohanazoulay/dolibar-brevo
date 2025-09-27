<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Administration page to configure Brevo API key.
 */

require __DIR__.'/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/brevointegration/class/BrevoClient.class.php');
dol_include_once('/brevointegration/class/services/brevofieldmappingservice.class.php');
dol_include_once('/brevointegration/class/services/brevocategorymappingservice.class.php');
dol_include_once('/brevointegration/class/services/brevologservice.class.php');
dol_include_once('/brevointegration/class/services/brevodatabasemaintenanceservice.class.php');

global $langs, $user, $conf, $db;

/**
 * @param string $attributeKey
 * @param string $fieldKey
 * @return array<int,array<string,string>>
 */
function brevointegration_parse_mapping_from_post($attributeKey, $fieldKey)
{
    $attributes = GETPOST($attributeKey, 'array');
    $fields = GETPOST($fieldKey, 'array');

    $entries = array();
    if (!is_array($attributes) || !is_array($fields)) {
        return $entries;
    }

    $count = max(count($attributes), count($fields));
    for ($i = 0; $i < $count; $i++) {
        $attribute = isset($attributes[$i]) ? trim((string) $attributes[$i]) : '';
        $fieldValue = isset($fields[$i]) ? trim((string) $fields[$i]) : '';

        if ($attribute === '' || $fieldValue === '') {
            continue;
        }

        $parts = explode(':', $fieldValue, 2);
        $source = isset($parts[0]) ? $parts[0] : '';
        $field = isset($parts[1]) ? $parts[1] : '';

        if ($source === '' || $field === '') {
            continue;
        }

        $attribute = strtoupper($attribute);
        $attribute = preg_replace('/[^A-Z0-9_]/', '_', $attribute);
        if ($attribute === '') {
            continue;
        }

        $entries[] = array(
            'attribute' => $attribute,
            'source' => $source === 'extrafield' ? 'extrafield' : 'standard',
            'field' => $field,
        );
    }

    return $entries;
}

/**
 * @param string $categoryKey
 * @param string $listKey
 * @return array<int,array<string,int>>
 */
function brevointegration_parse_category_mapping_from_post($categoryKey, $listKey)
{
    $categories = GETPOST($categoryKey, 'array');
    $lists = GETPOST($listKey, 'array');

    if (!is_array($categories) || !is_array($lists)) {
        return array();
    }

    $entries = array();
    $count = max(count($categories), count($lists));
    for ($i = 0; $i < $count; $i++) {
        $categoryId = isset($categories[$i]) ? (int) $categories[$i] : 0;
        $listId = isset($lists[$i]) ? (int) $lists[$i] : 0;

        if ($categoryId <= 0 || $listId <= 0) {
            continue;
        }

        $entries[] = array(
            'category_id' => $categoryId,
            'list_id' => $listId,
        );
    }

    return $entries;
}

/**
 * Render a status icon with label for diagnostic checks.
 *
 * @param string    $status ok|ko|warning
 * @param Translate $langs  Translator
 * @return string
 */
function brevointegration_render_status_icon($status, $langs)
{
    $status = strtolower((string) $status);
    if ($status === 'ok') {
        return img_picto($langs->trans('BrevoDiagnosticStatusOk'), 'tick').' '.$langs->trans('BrevoDiagnosticStatusOk');
    }

    if ($status === 'warning') {
        return img_picto($langs->trans('BrevoDiagnosticStatusWarning'), 'warning').' '.$langs->trans('BrevoDiagnosticStatusWarning');
    }

    return img_picto($langs->trans('BrevoDiagnosticStatusKo'), 'error').' '.$langs->trans('BrevoDiagnosticStatusKo');
}

/**
 * Mask an API key while keeping extremities visible.
 *
 * @param string $apiKey Raw API key
 * @return string
 */
function brevointegration_mask_api_key($apiKey)
{
    $apiKey = trim((string) $apiKey);
    $length = strlen($apiKey);
    if ($length <= 8) {
        return str_repeat('*', $length);
    }

    return substr($apiKey, 0, 4).'…'.substr($apiKey, -4);
}

/**
 * Check if a database table exists.
 *
 * @param DoliDB $db    Database handler
 * @param string $table Fully qualified table name
 * @return bool
 */
function brevointegration_table_exists($db, $table)
{
    if (!is_object($db)) {
        return false;
    }

    if (method_exists($db, 'DDLDescTable')) {
        $info = $db->DDLDescTable($table, '', '', true);
        if (is_array($info) && isset($info['fields']) && !empty($info['fields'])) {
            return true;
        }
    }

    if (method_exists($db, 'table_exists')) {
        return $db->table_exists($table) ? true : false;
    }

    if (method_exists($db, 'query')) {
        $sql = 'SELECT 1 FROM '.$table.' WHERE 1=0';
        $resql = $db->query($sql);
        if ($resql !== false) {
            if (method_exists($db, 'free')) {
                $db->free($resql);
            }

            return true;
        }
    }

    return false;
}

if (!$user->admin) {
    accessforbidden();
}

$langs->loadLangs(array('admin', 'other', 'brevointegration@brevointegration'));

$action = GETPOST('action', 'aZ09');
$selectedTab = GETPOST('tab', 'alpha');
$mappingService = new BrevoFieldMappingService($db, $conf);
$categoryMappingService = new BrevoCategoryMappingService($db, $conf);
$maintenanceService = new BrevoDatabaseMaintenanceService($db);
$generatedPatchSql = array();

if ($selectedTab === '') {
    $selectedTab = 'configuration';
}

$availableTabs = array('configuration', 'diagnostic');
if (!in_array($selectedTab, $availableTabs, true)) {
    $selectedTab = 'configuration';
}

try {
    if ($action === 'saveapikey') {
        if (!checkToken()) {
            accessforbidden();
        }

        $apiKey = trim(GETPOST('BREVO_APIKEY', 'restricthtml'));
        if ($apiKey === '') {
            dolibarr_del_const($db, 'BREVO_APIKEY', $conf->entity);
            unset($conf->global->BREVO_APIKEY);
            setEventMessages($langs->trans('BrevoApiKeyRemoved'), null, 'mesgs');
        } else {
            dolibarr_set_const($db, 'BREVO_APIKEY', $apiKey, 'chaine', 0, '', $conf->entity);
            $conf->global->BREVO_APIKEY = $apiKey;
            setEventMessages($langs->trans('BrevoApiKeySaved'), null, 'mesgs');
        }

        $action = '';
    } elseif ($action === 'testapikey') {
        if (!checkToken()) {
            accessforbidden();
        }

        $apiKey = isset($conf->global->BREVO_APIKEY) ? trim((string) $conf->global->BREVO_APIKEY) : '';
        if ($apiKey === '') {
            setEventMessages($langs->trans('BrevoApiKeyInvalid'), null, 'errors');
        } else {
            $client = new BrevoClient($db, $conf, $apiKey);
            $result = $client->validateApiKey($apiKey);

            $httpCode = isset($result['http_code']) ? (int) $result['http_code'] : 0;
            $duration = isset($result['duration_ms']) ? (int) $result['duration_ms'] : 0;
            $success = !empty($result['success']);
            $errorMessage = isset($result['error']) && is_string($result['error']) ? trim($result['error']) : '';

            $logService = new BrevoLogService($db, $conf);
            $logService->record('GET', '/v3/account', $httpCode, $duration, $success, $errorMessage);

            if ($success) {
                setEventMessages($langs->trans('BrevoConnectionOk', $httpCode, $duration), null, 'mesgs');
            } else {
                $displayMessage = $errorMessage !== '' ? $errorMessage : $langs->trans('BrevoConnectionFail');
                setEventMessages($langs->trans('BrevoConnectionFailWithDetails', $displayMessage, $httpCode, $duration), null, 'errors');
            }
        }

        $action = '';
    }
} catch (Throwable $exception) {
    dol_syslog(__FILE__.'::setup_action '.$exception->getMessage(), LOG_ERR);
    setEventMessages($langs->trans('ErrorInternalError'), null, 'errors');
    $action = '';
}

if ($action === 'savefieldmapping') {
    if (!checkToken()) {
        accessforbidden();
    }

    $mapping = array(
        'contact' => brevointegration_parse_mapping_from_post('brevo_attribute_contact', 'brevo_field_contact'),
        'thirdparty' => brevointegration_parse_mapping_from_post('brevo_attribute_thirdparty', 'brevo_field_thirdparty'),
    );

    if ($mappingService->saveMapping($mapping)) {
        setEventMessages($langs->trans('BrevoFieldMappingSaved'), null, 'mesgs');
    } else {
        setEventMessages($langs->trans('BrevoFieldMappingSaveError'), null, 'errors');
    }
} elseif ($action === 'savecategorymapping') {
    if (!checkToken()) {
        accessforbidden();
    }

    $entries = brevointegration_parse_category_mapping_from_post('brevo_category_id', 'brevo_category_list_id');
    if ($categoryMappingService->saveMappings($entries)) {
        setEventMessages($langs->trans('BrevoCategoryMappingSaved'), null, 'mesgs');
    } else {
        setEventMessages($langs->trans('BrevoCategoryMappingSaveError'), null, 'errors');
    }
} elseif ($action === 'generatepatch') {
    if (!checkToken()) {
        accessforbidden();
    }

    $selectedTab = 'diagnostic';
    $statuses = array(
        'log' => $maintenanceService->getLogTableStatus(),
        'contactsync' => $maintenanceService->getContactSyncTableStatus(),
    );

    $generatedPatchSql = $maintenanceService->buildPatch($statuses);
    if (empty($generatedPatchSql)) {
        setEventMessages($langs->trans('BrevoDiagnosticNoPatchNeeded'), null, 'mesgs');
    } else {
        setEventMessages($langs->trans('BrevoDiagnosticPatchGenerated'), null, 'mesgs');
    }
}

$helpUrl = '';
llxHeader('', $langs->trans('BrevoSetupTitle'), $helpUrl);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('BrevoSetupTitle'), $linkback, 'icon-picto-brevo.svg@brevointegration');

$baseUrl = dol_buildpath('/brevointegration/admin/setup.php', 1);
$head = array();
$head[] = array($baseUrl.'?tab=configuration', $langs->trans('BrevoSetupTabConfiguration'), 'configuration');
$head[] = array($baseUrl.'?tab=diagnostic', $langs->trans('BrevoSetupTabDiagnostic'), 'diagnostic');

dol_fiche_head($head, $selectedTab, $langs->trans('BrevoSetupTitle'), -1, 'icon-picto-brevo.svg@brevointegration');

if ($selectedTab === 'configuration') {
    $token = newToken();
    $testToken = newToken();
    $mappingToken = newToken();
    $categoryToken = newToken();
    $contactMapping = $mappingService->getMappingForType('contact');
    $thirdpartyMapping = $mappingService->getMappingForType('thirdparty');
    $contactMapping[] = array('attribute' => '', 'source' => '', 'field' => '');
    $thirdpartyMapping[] = array('attribute' => '', 'source' => '', 'field' => '');
    $contactFields = $mappingService->getAvailableFields('contact');
    $thirdpartyFields = $mappingService->getAvailableFields('thirdparty');
    $categoryMappings = $categoryMappingService->getMappings();
    $categoryMappings[] = array('category_id' => 0, 'list_id' => 0);
    $availableCategories = $categoryMappingService->getAllContactCategories();
    $categoryLists = array();
    $categoryListError = '';
    $apiKeyForLists = isset($conf->global->BREVO_APIKEY) ? trim((string) $conf->global->BREVO_APIKEY) : '';
    if ($apiKeyForLists === '') {
        $categoryListError = $langs->trans('BrevoMissingApiKey');
    } else {
        $listsApi = new BrevoClient($db, $conf, $apiKeyForLists);
        $listsResponse = $listsApi->getLists(200, 0);
        if (!empty($listsResponse['success']) && !empty($listsResponse['data']['lists'])) {
            foreach ($listsResponse['data']['lists'] as $list) {
                if (!isset($list['id'])) {
                    continue;
                }
                $listId = (int) $list['id'];
                $categoryLists[$listId] = isset($list['name']) ? (string) $list['name'] : '';
            }
        } elseif (!empty($listsResponse['error'])) {
            $categoryListError = $listsResponse['error'];
        }
    }
    $supportInfo = sprintf(
        $langs->trans('BrevoModuleSupportInfo'),
        '<a href="https://meditrust.io" target="_blank" rel="noopener noreferrer">',
        '</a>',
        '<a href="mailto:yohan@meditrust.io">yohan@meditrust.io</a>'
    );

    print '<div class="opacitymedium mtoponly">'.$supportInfo.'</div>';
    print '<div class="fichecenter">';
    print '    <div class="fichehalfleft">';
    print '        <div class="box">';
    print '            <h3>'.$langs->trans('BrevoModuleGuideTitle').'</h3>';
    print '            <p>'.$langs->trans('BrevoModuleGuideIntro').'</p>';
    print '            <ol>';
    print '                <li>'.$langs->trans('BrevoModuleGuideStep1').'</li>';
    print '                <li>'.$langs->trans('BrevoModuleGuideStep2').'</li>';
    print '                <li>'.$langs->trans('BrevoModuleGuideStep3').'</li>';
    print '            </ol>';
    print '        </div>';
    print '    </div>';
    print '    <div class="fichehalfright">';
    print '        <div class="box">';
    print '            <h3>'.$langs->trans('BrevoModuleGuideBenefitsTitle').'</h3>';
    print '            <ul>';
    print '                <li>'.$langs->trans('BrevoModuleGuideBenefit1').'</li>';
    print '                <li>'.$langs->trans('BrevoModuleGuideBenefit2').'</li>';
    print '                <li>'.$langs->trans('BrevoModuleGuideBenefit3').'</li>';
    print '            </ul>';
    print '        </div>';
    print '    </div>';
    print '    <div class="clearboth"></div>';
    print '</div>';
    ?>
    <form action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" method="post" class="form-horizontal">
        <input type="hidden" name="token" value="<?php echo $token; ?>" />
        <input type="hidden" name="action" value="saveapikey" />
        <input type="hidden" name="tab" value="configuration" />
        <table class="noborder" width="100%">
            <tr class="liste_titre">
                <th><?php echo $langs->trans('Parameter'); ?></th>
                <th><?php echo $langs->trans('Value'); ?></th>
            </tr>
            <tr>
                <td class="fieldrequired"><?php echo $langs->trans('BrevoApiKeyLabel'); ?></td>
                <td>
                    <input type="text" name="BREVO_APIKEY" size="60" value="<?php echo dol_escape_htmltag(isset($conf->global->BREVO_APIKEY) ? $conf->global->BREVO_APIKEY : ''); ?>" />
                </td>
            </tr>
        </table>
        <div class="center">
            <input type="submit" class="button" value="<?php echo dol_escape_htmltag($langs->trans('BrevoSave')); ?>" />
        </div>
    </form>
    <form action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" method="post" class="center mtop">
        <input type="hidden" name="token" value="<?php echo $testToken; ?>" />
        <input type="hidden" name="action" value="testapikey" />
        <input type="hidden" name="tab" value="configuration" />
        <input type="submit" class="button" value="<?php echo dol_escape_htmltag($langs->trans('BrevoTestConnection')); ?>" />
    </form>
    <h3><?php echo dol_escape_htmltag($langs->trans('BrevoFieldMappingTitle')); ?></h3>
    <p class="opacitymedium"><?php echo $langs->trans('BrevoFieldMappingIntro'); ?></p>
    <form action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" method="post" class="form-horizontal">
        <input type="hidden" name="token" value="<?php echo $mappingToken; ?>" />
        <input type="hidden" name="action" value="savefieldmapping" />
        <input type="hidden" name="tab" value="configuration" />
        <table class="noborder" width="100%">
            <tr class="liste_titre">
                <th colspan="2"><?php echo dol_escape_htmltag($langs->trans('BrevoFieldMappingContactTitle')); ?></th>
            </tr>
            <tr class="liste_titre">
                <th><?php echo dol_escape_htmltag($langs->trans('BrevoFieldMappingAttribute')); ?></th>
                <th><?php echo dol_escape_htmltag($langs->trans('BrevoFieldMappingField')); ?></th>
            </tr>
            <?php foreach ($contactMapping as $entry) { ?>
                <tr class="oddeven">
                    <td>
                        <input type="text" name="brevo_attribute_contact[]" value="<?php echo dol_escape_htmltag($entry['attribute']); ?>" size="30" />
                    </td>
                    <td>
                        <select name="brevo_field_contact[]">
                            <option value=""><?php echo dol_escape_htmltag($langs->trans('BrevoFieldMappingSelectField')); ?></option>
                            <?php if (!empty($contactFields['standard'])) { ?>
                                <optgroup label="<?php echo dol_escape_htmltag($langs->trans('BrevoFieldMappingStandardGroup')); ?>">
                                    <?php foreach ($contactFields['standard'] as $field => $label) { ?>
                                        <?php $selected = ($entry['source'] === 'standard' && $entry['field'] === $field) ? ' selected="selected"' : ''; ?>
                                        <option value="standard:<?php echo dol_escape_htmltag($field); ?>"<?php echo $selected; ?>><?php echo dol_escape_htmltag($label); ?></option>
                                    <?php } ?>
                                </optgroup>
                            <?php } ?>
                            <?php if (!empty($contactFields['extrafields'])) { ?>
                                <optgroup label="<?php echo dol_escape_htmltag($langs->trans('BrevoFieldMappingExtraGroup')); ?>">
                                    <?php foreach ($contactFields['extrafields'] as $field => $label) { ?>
                                        <?php $selected = ($entry['source'] === 'extrafield' && $entry['field'] === $field) ? ' selected="selected"' : ''; ?>
                                        <option value="extrafield:<?php echo dol_escape_htmltag($field); ?>"<?php echo $selected; ?>><?php echo dol_escape_htmltag($label); ?></option>
                                    <?php } ?>
                                </optgroup>
                            <?php } ?>
                        </select>
                    </td>
                </tr>
            <?php } ?>
            <tr class="liste_titre">
                <th colspan="2"><?php echo dol_escape_htmltag($langs->trans('BrevoFieldMappingThirdpartyTitle')); ?></th>
            </tr>
            <tr class="liste_titre">
                <th><?php echo dol_escape_htmltag($langs->trans('BrevoFieldMappingAttribute')); ?></th>
                <th><?php echo dol_escape_htmltag($langs->trans('BrevoFieldMappingField')); ?></th>
            </tr>
            <?php foreach ($thirdpartyMapping as $entry) { ?>
                <tr class="oddeven">
                    <td>
                        <input type="text" name="brevo_attribute_thirdparty[]" value="<?php echo dol_escape_htmltag($entry['attribute']); ?>" size="30" />
                    </td>
                    <td>
                        <select name="brevo_field_thirdparty[]">
                            <option value=""><?php echo dol_escape_htmltag($langs->trans('BrevoFieldMappingSelectField')); ?></option>
                            <?php if (!empty($thirdpartyFields['standard'])) { ?>
                                <optgroup label="<?php echo dol_escape_htmltag($langs->trans('BrevoFieldMappingStandardGroup')); ?>">
                                    <?php foreach ($thirdpartyFields['standard'] as $field => $label) { ?>
                                        <?php $selected = ($entry['source'] === 'standard' && $entry['field'] === $field) ? ' selected="selected"' : ''; ?>
                                        <option value="standard:<?php echo dol_escape_htmltag($field); ?>"<?php echo $selected; ?>><?php echo dol_escape_htmltag($label); ?></option>
                                    <?php } ?>
                                </optgroup>
                            <?php } ?>
                            <?php if (!empty($thirdpartyFields['extrafields'])) { ?>
                                <optgroup label="<?php echo dol_escape_htmltag($langs->trans('BrevoFieldMappingExtraGroup')); ?>">
                                    <?php foreach ($thirdpartyFields['extrafields'] as $field => $label) { ?>
                                        <?php $selected = ($entry['source'] === 'extrafield' && $entry['field'] === $field) ? ' selected="selected"' : ''; ?>
                                        <option value="extrafield:<?php echo dol_escape_htmltag($field); ?>"<?php echo $selected; ?>><?php echo dol_escape_htmltag($label); ?></option>
                                    <?php } ?>
                                </optgroup>
                            <?php } ?>
                        </select>
                    </td>
                </tr>
            <?php } ?>
            <tr class="oddeven">
                <td colspan="2" class="opacitymedium"><?php echo $langs->trans('BrevoFieldMappingAddHint'); ?></td>
            </tr>
        </table>
        <div class="center">
            <input type="submit" class="button" value="<?php echo dol_escape_htmltag($langs->trans('Save')); ?>" />
        </div>
    </form>
    <h3><?php echo dol_escape_htmltag($langs->trans('BrevoCategoryMappingTitle')); ?></h3>
    <p class="opacitymedium"><?php echo $langs->trans('BrevoCategoryMappingIntro'); ?></p>
    <?php if ($categoryListError !== '') { ?>
        <div class="warning"><?php echo dol_escape_htmltag($categoryListError); ?></div>
    <?php } ?>
    <?php if (empty($availableCategories)) { ?>
        <div class="opacitymedium"><?php echo dol_escape_htmltag($langs->trans('BrevoCategoryMappingNoCategories')); ?></div>
    <?php } ?>
    <?php if ($categoryListError === '' && empty($categoryLists)) { ?>
        <div class="opacitymedium"><?php echo dol_escape_htmltag($langs->trans('BrevoCategoryMappingNoLists')); ?></div>
    <?php } ?>
    <form action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" method="post" class="form-horizontal">
        <input type="hidden" name="token" value="<?php echo $categoryToken; ?>" />
        <input type="hidden" name="action" value="savecategorymapping" />
        <input type="hidden" name="tab" value="configuration" />
        <table class="noborder" width="100%">
            <tr class="liste_titre">
                <th><?php echo dol_escape_htmltag($langs->trans('BrevoCategoryMappingCategory')); ?></th>
                <th><?php echo dol_escape_htmltag($langs->trans('BrevoCategoryMappingList')); ?></th>
            </tr>
            <?php foreach ($categoryMappings as $entry) { ?>
                <tr class="oddeven">
                    <td>
                        <select name="brevo_category_id[]">
                            <option value="0"><?php echo dol_escape_htmltag($langs->trans('Select')); ?></option>
                            <?php foreach ($availableCategories as $categoryId => $categoryLabel) { ?>
                                <?php $selected = ((int) $entry['category_id'] === (int) $categoryId) ? ' selected="selected"' : ''; ?>
                                <option value="<?php echo (int) $categoryId; ?>"<?php echo $selected; ?>><?php echo dol_escape_htmltag($categoryLabel); ?></option>
                            <?php } ?>
                        </select>
                    </td>
                    <td>
                        <select name="brevo_category_list_id[]">
                            <option value="0"><?php echo dol_escape_htmltag($langs->trans('Select')); ?></option>
                            <?php foreach ($categoryLists as $listId => $listLabel) { ?>
                                <?php $selected = ((int) $entry['list_id'] === (int) $listId) ? ' selected="selected"' : ''; ?>
                                <option value="<?php echo (int) $listId; ?>"<?php echo $selected; ?>><?php echo dol_escape_htmltag($listLabel !== '' ? $listLabel : ('#'.$listId)); ?></option>
                            <?php } ?>
                        </select>
                    </td>
                </tr>
            <?php } ?>
            <tr class="oddeven">
                <td colspan="2" class="opacitymedium"><?php echo $langs->trans('BrevoCategoryMappingAddHint'); ?></td>
            </tr>
        </table>
        <div class="center">
            <input type="submit" class="button" value="<?php echo dol_escape_htmltag($langs->trans('Save')); ?>" />
        </div>
    </form>
    <?php
} elseif ($selectedTab === 'diagnostic') {
    dol_include_once('/brevointegration/core/modules/modBrevointegration.class.php');
    $moduleDescriptor = new modBrevointegration($db);
    $moduleVersion = $moduleDescriptor->version;
    $logService = new BrevoLogService($db, $conf);

    $sections = array(
        'module' => array('title' => $langs->trans('BrevoDiagnosticSectionModule'), 'checks' => array()),
        'environment' => array('title' => $langs->trans('BrevoDiagnosticSectionEnvironment'), 'checks' => array()),
        'database' => array('title' => $langs->trans('BrevoDiagnosticSectionDatabase'), 'checks' => array()),
        'configuration' => array('title' => $langs->trans('BrevoDiagnosticSectionConfiguration'), 'checks' => array()),
        'filesystem' => array('title' => $langs->trans('BrevoDiagnosticSectionFilesystem'), 'checks' => array()),
    );

    $isEnabled = isset($conf->brevointegration->enabled) && (int) $conf->brevointegration->enabled === 1;
    $sections['module']['checks'][] = array(
        'label' => $langs->trans('BrevoDiagnosticModuleEnabled'),
        'status' => $isEnabled ? 'ok' : 'warning',
        'details' => $isEnabled ? $langs->trans('Yes') : $langs->trans('No')
    );
    $sections['module']['checks'][] = array(
        'label' => $langs->trans('BrevoDiagnosticVersionLabel'),
        'status' => 'ok',
        'details' => dol_escape_htmltag((string) $moduleVersion)
    );
    if (defined('DOL_VERSION')) {
        $sections['module']['checks'][] = array(
            'label' => $langs->trans('BrevoDiagnosticDolibarrVersion'),
            'status' => 'ok',
            'details' => dol_escape_htmltag((string) DOL_VERSION)
        );
    }

    $minimumPhpVersion = '7.4.0';
    $currentPhpVersion = PHP_VERSION;
    $phpOk = version_compare($currentPhpVersion, $minimumPhpVersion, '>=');
    $sections['environment']['checks'][] = array(
        'label' => $langs->trans('BrevoDiagnosticPhpVersion'),
        'status' => $phpOk ? 'ok' : 'ko',
        'details' => $langs->trans('BrevoDiagnosticPhpVersionDetail', $currentPhpVersion, $minimumPhpVersion)
    );

    $extensions = array(
        'curl' => 'BrevoDiagnosticCurlExtension',
        'json' => 'BrevoDiagnosticJsonExtension',
        'mbstring' => 'BrevoDiagnosticMbstringExtension',
    );
    foreach ($extensions as $extension => $labelKey) {
        $loaded = extension_loaded($extension);
        $sections['environment']['checks'][] = array(
            'label' => $langs->trans($labelKey),
            'status' => $loaded ? 'ok' : 'ko',
            'details' => $loaded ? $langs->trans('BrevoDiagnosticExtensionLoaded') : $langs->trans('BrevoDiagnosticExtensionMissing')
        );
    }

    $apiKey = isset($conf->global->BREVO_APIKEY) ? trim((string) $conf->global->BREVO_APIKEY) : '';
    if ($apiKey === '') {
        $sections['configuration']['checks'][] = array(
            'label' => $langs->trans('BrevoDiagnosticApiKeyConfigured'),
            'status' => 'warning',
            'details' => $langs->trans('BrevoDiagnosticApiKeyMissing')
        );
    } else {
        $sections['configuration']['checks'][] = array(
            'label' => $langs->trans('BrevoDiagnosticApiKeyConfigured'),
            'status' => 'ok',
            'details' => $langs->trans('BrevoDiagnosticApiKeyMasked', brevointegration_mask_api_key($apiKey))
        );

        if (function_exists('curl_init')) {
            try {
                $client = new BrevoClient($db, $conf, $apiKey);
                $validation = $client->validateApiKey($apiKey);
                if (!empty($validation['success'])) {
                    $sections['configuration']['checks'][] = array(
                        'label' => $langs->trans('BrevoDiagnosticApiKeyValidation'),
                        'status' => 'ok',
                        'details' => $langs->trans('BrevoDiagnosticApiKeyValidationOk')
                    );
                } else {
                    $errorMessage = isset($validation['error']) ? (string) $validation['error'] : '';
                    if ($errorMessage === '') {
                        $errorMessage = $langs->trans('Error');
                    }
                    $sections['configuration']['checks'][] = array(
                        'label' => $langs->trans('BrevoDiagnosticApiKeyValidation'),
                        'status' => 'warning',
                        'details' => $langs->trans('BrevoDiagnosticApiKeyValidationKo', $errorMessage)
                    );
                }
            } catch (Exception $exception) {
                $sections['configuration']['checks'][] = array(
                    'label' => $langs->trans('BrevoDiagnosticApiKeyValidation'),
                    'status' => 'warning',
                    'details' => $langs->trans('BrevoDiagnosticApiKeyValidationKo', $exception->getMessage())
                );
            }
        } else {
            $sections['configuration']['checks'][] = array(
                'label' => $langs->trans('BrevoDiagnosticApiKeyValidation'),
                'status' => 'warning',
                'details' => $langs->trans('BrevoDiagnosticApiKeyValidationSkipped')
            );
        }
    }

    $rawMapping = isset($conf->global->{BrevoFieldMappingService::CONST_NAME}) ? (string) $conf->global->{BrevoFieldMappingService::CONST_NAME} : '';
    if ($rawMapping === '') {
        $sections['configuration']['checks'][] = array(
            'label' => $langs->trans('BrevoDiagnosticMappingConst'),
            'status' => 'warning',
            'details' => $langs->trans('BrevoDiagnosticConstEmpty')
        );
    } else {
        $decoded = json_decode($rawMapping, true);
        if (is_array($decoded)) {
            $sections['configuration']['checks'][] = array(
                'label' => $langs->trans('BrevoDiagnosticMappingConst'),
                'status' => 'ok',
                'details' => $langs->trans('BrevoDiagnosticConstValid')
            );
        } else {
            $sections['configuration']['checks'][] = array(
                'label' => $langs->trans('BrevoDiagnosticMappingConst'),
                'status' => 'ko',
                'details' => $langs->trans('BrevoDiagnosticConstInvalid', json_last_error_msg())
            );
        }
    }

    $rawCategoryMapping = isset($conf->global->{BrevoCategoryMappingService::CONST_NAME}) ? (string) $conf->global->{BrevoCategoryMappingService::CONST_NAME} : '';
    if ($rawCategoryMapping === '') {
        $sections['configuration']['checks'][] = array(
            'label' => $langs->trans('BrevoDiagnosticCategoryConst'),
            'status' => 'warning',
            'details' => $langs->trans('BrevoDiagnosticConstEmpty')
        );
    } else {
        $decodedCategory = json_decode($rawCategoryMapping, true);
        if (is_array($decodedCategory)) {
            $sections['configuration']['checks'][] = array(
                'label' => $langs->trans('BrevoDiagnosticCategoryConst'),
                'status' => 'ok',
                'details' => $langs->trans('BrevoDiagnosticConstValid')
            );
        } else {
            $sections['configuration']['checks'][] = array(
                'label' => $langs->trans('BrevoDiagnosticCategoryConst'),
                'status' => 'ko',
                'details' => $langs->trans('BrevoDiagnosticConstInvalid', json_last_error_msg())
            );
        }
    }

    $logStatus = $logService->getLogStorageStatus();
    $sections['database']['checks'][] = array(
        'label' => $langs->trans('BrevoDiagnosticLogTable'),
        'status' => $logStatus['exists'] ? 'ok' : 'ko',
        'details' => $logStatus['exists'] ? $langs->trans('BrevoDiagnosticTableExists', $logStatus['table_name']) : $langs->trans('BrevoDiagnosticTableMissing', $logStatus['table_name'])
    );
    if ($logStatus['exists']) {
        $logColumnsList = empty($logStatus['available_columns']) ? $langs->trans('BrevoDiagnosticColumnsListEmpty') : implode(', ', $logStatus['available_columns']);
        $logColumnsDetail = empty($logStatus['missing_columns']) ? $langs->trans('BrevoDiagnosticColumnsOk') : $langs->trans('BrevoDiagnosticColumnsMissing', implode(', ', $logStatus['missing_columns']));
        $logColumnsDetail .= ' — '.$langs->trans('BrevoDiagnosticColumnsList', $logColumnsList);
        $sections['database']['checks'][] = array(
            'label' => $langs->trans('BrevoDiagnosticLogTableColumns'),
            'status' => empty($logStatus['missing_columns']) ? 'ok' : 'ko',
            'details' => $logColumnsDetail
        );

        $logCountSql = 'SELECT COUNT(*) as total FROM '.$logStatus['table_name'].' WHERE 1=0';
        $resql = $db->query($logCountSql);
        if ($resql === false) {
            $sections['database']['checks'][] = array(
                'label' => $langs->trans('BrevoDiagnosticLogTableAccess'),
                'status' => 'ko',
                'details' => $langs->trans('BrevoDiagnosticQueryFailed', $db->lasterror())
            );
        } else {
            if (method_exists($db, 'free')) {
                $db->free($resql);
            }
            $sections['database']['checks'][] = array(
                'label' => $langs->trans('BrevoDiagnosticLogTableAccess'),
                'status' => 'ok',
                'details' => $langs->trans('BrevoDiagnosticQueryOk')
            );
        }

        $logCrudStatus = $logService->testLogTableWriteOperations();
        if ($logCrudStatus['supported']) {
            $crudDetails = array();
            $operationsLabels = array(
                'insert' => $langs->trans('BrevoDiagnosticCrudInsert'),
                'update' => $langs->trans('BrevoDiagnosticCrudUpdate'),
                'delete' => $langs->trans('BrevoDiagnosticCrudDelete'),
            );
            foreach ($operationsLabels as $operation => $labelText) {
                $operationResult = isset($logCrudStatus['operations'][$operation]) ? $logCrudStatus['operations'][$operation] : array('success' => false, 'message' => '');
                if (!empty($operationResult['success'])) {
                    if ($operationResult['message'] !== '') {
                        $crudDetails[] = $langs->trans('BrevoDiagnosticCrudOperationOkWithDetail', $labelText, $operationResult['message']);
                    } else {
                        $crudDetails[] = $langs->trans('BrevoDiagnosticCrudOperationOk', $labelText);
                    }
                } else {
                    if (isset($operationResult['message']) && $operationResult['message'] === 'missing_identifier') {
                        $message = $langs->trans('BrevoDiagnosticCrudErrorMissingIdentifier');
                    } elseif (!empty($operationResult['message'])) {
                        $message = $operationResult['message'];
                    } else {
                        $message = $langs->trans('BrevoDiagnosticCrudOperationKoUnknown');
                    }
                    $crudDetails[] = $langs->trans('BrevoDiagnosticCrudOperationKo', $labelText, $message);
                }
            }

            $sections['database']['checks'][] = array(
                'label' => $langs->trans('BrevoDiagnosticLogCrud'),
                'status' => $logCrudStatus['success'] ? 'ok' : 'ko',
                'details' => implode(' — ', $crudDetails)
            );
        } else {
            $errorDetails = '';
            switch ($logCrudStatus['error']) {
                case 'db_unavailable':
                    $errorDetails = $langs->trans('BrevoDiagnosticCrudErrorDb');
                    break;
                case 'log_table_missing':
                    $errorDetails = $langs->trans('BrevoDiagnosticTableMissing', $logStatus['table_name']);
                    break;
                case 'log_table_incomplete':
                    $errorDetails = $langs->trans('BrevoDiagnosticCrudErrorIncomplete');
                    break;
                case 'missing_column':
                    $columnName = isset($logCrudStatus['error_details']) && $logCrudStatus['error_details'] !== '' ? $logCrudStatus['error_details'] : '?';
                    $errorDetails = $langs->trans('BrevoDiagnosticCrudErrorMissingColumn', $columnName);
                    break;
                case 'insert_failed':
                    $errorDetails = $langs->trans('BrevoDiagnosticCrudErrorInsert');
                    break;
                case 'update_failed':
                    $errorDetails = $langs->trans('BrevoDiagnosticCrudErrorUpdate');
                    break;
                case 'delete_failed':
                    $errorDetails = $langs->trans('BrevoDiagnosticCrudErrorDelete');
                    break;
                case 'exception':
                    $details = isset($logCrudStatus['error_details']) ? $logCrudStatus['error_details'] : '';
                    $errorDetails = $langs->trans('BrevoDiagnosticCrudErrorException', $details);
                    break;
                default:
                    $errorDetails = $langs->trans('BrevoDiagnosticCrudSkipped');
                    break;
            }

            $sections['database']['checks'][] = array(
                'label' => $langs->trans('BrevoDiagnosticLogCrud'),
                'status' => 'warning',
                'details' => $errorDetails
            );
        }
    }

    $contactStatus = $maintenanceService->getContactSyncTableStatus();
    $sections['database']['checks'][] = array(
        'label' => $langs->trans('BrevoDiagnosticContactSyncTable'),
        'status' => $contactStatus['exists'] ? 'ok' : 'warning',
        'details' => $contactStatus['exists'] ? $langs->trans('BrevoDiagnosticTableExists', $contactStatus['table_name']) : $langs->trans('BrevoDiagnosticTableMissing', $contactStatus['table_name'])
    );
    if ($contactStatus['exists']) {
        $contactColumnsList = empty($contactStatus['available_columns']) ? $langs->trans('BrevoDiagnosticColumnsListEmpty') : implode(', ', $contactStatus['available_columns']);
        $contactColumnsDetail = empty($contactStatus['missing_columns']) ? $langs->trans('BrevoDiagnosticColumnsOk') : $langs->trans('BrevoDiagnosticColumnsMissing', implode(', ', $contactStatus['missing_columns']));
        $contactColumnsDetail .= ' — '.$langs->trans('BrevoDiagnosticColumnsList', $contactColumnsList);
        $sections['database']['checks'][] = array(
            'label' => $langs->trans('BrevoDiagnosticContactSyncColumns'),
            'status' => empty($contactStatus['missing_columns']) ? 'ok' : 'ko',
            'details' => $contactColumnsDetail
        );
    }

    $logsPagePath = dol_buildpath('/brevointegration/admin/logs.php', 0);
    $logsPageExists = is_string($logsPagePath) && is_file($logsPagePath);
    $sections['filesystem']['checks'][] = array(
        'label' => $langs->trans('BrevoDiagnosticLogsPage'),
        'status' => $logsPageExists ? 'ok' : 'ko',
        'details' => $logsPageExists ? $langs->trans('BrevoDiagnosticFileExists', $logsPagePath) : $langs->trans('BrevoDiagnosticFileMissing', $logsPagePath)
    );

    $expectedMainIncPath = __DIR__.'/../../../main.inc.php';
    $mainIncExists = is_file($expectedMainIncPath);
    $sections['filesystem']['checks'][] = array(
        'label' => $langs->trans('BrevoDiagnosticMainIncFile'),
        'status' => $mainIncExists ? 'ok' : 'ko',
        'details' => $mainIncExists ? $langs->trans('BrevoDiagnosticFileExists', $expectedMainIncPath) : $langs->trans('BrevoDiagnosticFileMissing', $expectedMainIncPath)
    );

    $iconPath = dol_buildpath('/brevointegration/img/icon-picto-brevo.svg', 0);
    if ($iconPath) {
        $iconExists = is_file($iconPath);
        $sections['filesystem']['checks'][] = array(
            'label' => $langs->trans('BrevoDiagnosticIconFile'),
            'status' => $iconExists ? 'ok' : 'warning',
            'details' => $iconExists ? $langs->trans('BrevoDiagnosticFileExists', $iconPath) : $langs->trans('BrevoDiagnosticFileMissing', $iconPath)
        );
    }

    print '<div class="opacitymedium mtoponly">'.$langs->trans('BrevoDiagnosticIntro').'</div>';
    print '<table class="noborder" width="100%">';
    print '    <tr class="liste_titre">';
    print '        <th>'.$langs->trans('BrevoDiagnosticParameter').'</th>';
    print '        <th>'.$langs->trans('BrevoDiagnosticStatus').'</th>';
    print '        <th>'.$langs->trans('BrevoDiagnosticDetails').'</th>';
    print '    </tr>';

    foreach ($sections as $sectionKey => $section) {
        if (empty($section['checks'])) {
            continue;
        }

        print '    <tr class="liste_titre">';
        print '        <th colspan="3">'.dol_escape_htmltag($section['title']).'</th>';
        print '    </tr>';

        foreach ($section['checks'] as $index => $check) {
            $class = ($index % 2 === 0) ? 'oddeven' : 'oddeven';
            $details = isset($check['details']) ? $check['details'] : '';
            print '    <tr class="'.$class.'">';
            print '        <td>'.dol_escape_htmltag($check['label']).'</td>';
            print '        <td>'.brevointegration_render_status_icon($check['status'], $langs).'</td>';
            print '        <td>'.dol_escape_htmltag($details).'</td>';
            print '    </tr>';
        }
    }

    print '</table>';

    $patchNeeded = (!$logStatus['ready'] || !$contactStatus['ready']);
    if ($patchNeeded) {
        $patchToken = newToken();
        print '<form action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" method="post" class="mtop25">';
        print '    <input type="hidden" name="token" value="'.$patchToken.'" />';
        print '    <input type="hidden" name="action" value="generatepatch" />';
        print '    <input type="hidden" name="tab" value="diagnostic" />';
        print '    <div class="center">';
        print '        <input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('BrevoDiagnosticGeneratePatch')).'" />';
        print '    </div>';
        print '</form>';
        print '<p class="opacitymedium center mtoponly">'.dol_escape_htmltag($langs->trans('BrevoDiagnosticPatchHint')).'</p>';
    }

    if (!empty($generatedPatchSql)) {
        print '<h3>'.dol_escape_htmltag($langs->trans('BrevoDiagnosticPatchTitle')).'</h3>';
        $patchContent = implode("\n", $generatedPatchSql);
        print '<textarea class="flat centpercent" rows="10" readonly="readonly">'.dol_escape_htmltag($patchContent)."\n".'</textarea>';
    }
}

dol_fiche_end();

llxFooter();
$db->close();
