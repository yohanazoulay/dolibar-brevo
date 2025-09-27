<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Administration page to configure Brevo API key.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/brevointegration/class/brevoapi.class.php');
dol_include_once('/brevointegration/class/services/brevofieldmappingservice.class.php');
dol_include_once('/brevointegration/class/services/brevocategorymappingservice.class.php');
dol_include_once('/brevointegration/class/services/brevologservice.class.php');

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
 * Render an HTML icon for diagnostic status.
 *
 * @param string   $status Status identifier (ok|warning|error)
 * @param Translate $langs Language handler
 * @return string
 */
function brevointegration_render_status_icon($status, $langs)
{
    switch ($status) {
        case 'ok':
            return img_picto($langs->trans('BrevoDiagnosticStatusOk'), 'tick');
        case 'warning':
            if (function_exists('img_warning')) {
                return img_warning($langs->trans('BrevoDiagnosticStatusWarning'));
            }

            return img_picto($langs->trans('BrevoDiagnosticStatusWarning'), 'warning');
        default:
            return img_picto($langs->trans('BrevoDiagnosticStatusKo'), 'error');
    }
}

/**
 * Print a diagnostic table with given checks.
 *
 * @param string $title  Table title
 * @param array  $checks List of checks (label,status,details)
 * @param Translate $langs Language handler
 * @return void
 */
function brevointegration_render_diagnostic_table($title, array $checks, $langs)
{
    if (empty($checks)) {
        return;
    }

    print '<div class="box">';
    print '    <h3>'.dol_escape_htmltag($title).'</h3>';
    print '    <table class="noborder" width="100%">';
    print '        <tr class="liste_titre">';
    print '            <th>'.$langs->trans('BrevoDiagnosticParameter').'</th>';
    print '            <th class="center">'.$langs->trans('BrevoDiagnosticStatus').'</th>';
    print '            <th>'.$langs->trans('BrevoDiagnosticDetails').'</th>';
    print '        </tr>';

    foreach ($checks as $check) {
        $rowClass = 'oddeven';
        $label = isset($check['label']) ? $check['label'] : '';
        $status = isset($check['status']) ? $check['status'] : 'error';
        $details = isset($check['details']) ? $check['details'] : '';

        print '        <tr class="'.$rowClass.'">';
        print '            <td>'.dol_escape_htmltag($label).'</td>';
        print '            <td class="center">'.brevointegration_render_status_icon($status, $langs).'</td>';
        print '            <td>'.dol_htmlentitiesbr($details).'</td>';
        print '        </tr>';
    }

    print '    </table>';
    print '</div>';
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

if (!$user->admin) {
    accessforbidden();
}

$langs->load('admin');
$langs->load('brevointegration@brevointegration');

$action = GETPOST('action', 'alpha');
$selectedTab = GETPOST('tab', 'alpha');
$mappingService = new BrevoFieldMappingService($db, $conf);
$categoryMappingService = new BrevoCategoryMappingService($db, $conf);

if ($selectedTab === '') {
    $selectedTab = 'configuration';
}

$availableTabs = array('configuration', 'diagnostic');
if (!in_array($selectedTab, $availableTabs, true)) {
    $selectedTab = 'configuration';
}

if ($action === 'setapikey') {
    if (!checkToken()) {
        accessforbidden();
    }

    $apiKey = trim(GETPOST('BREVOINTEGRATION_APIKEY', 'restricthtml'));
    if ($apiKey === '') {
        dolibarr_del_const($db, 'MAIN_BREVOINTEGRATION_APIKEY', $conf->entity);
        setEventMessages($langs->trans('BrevoApiKeyRemoved'), null, 'mesgs');
    } else {
        $api = new BrevoApi($db, $conf, $apiKey);
        $response = $api->validateApiKey($apiKey);
        if (!empty($response['success'])) {
            dolibarr_set_const($db, 'MAIN_BREVOINTEGRATION_APIKEY', $apiKey, 'chaine', 0, '', $conf->entity);
            setEventMessages($langs->trans('BrevoApiKeySaved'), null, 'mesgs');
        } else {
            $errorMessage = isset($response['error']) ? (string) $response['error'] : '';
            if ($errorMessage === 'Missing PHP cURL extension') {
                $errorMessage = $langs->trans('BrevoMissingCurlExtension');
            }
            if ($errorMessage === '') {
                $errorMessage = $langs->trans('Error');
            }
            setEventMessages($errorMessage, null, 'errors');
        }
    }
} elseif ($action === 'savefieldmapping') {
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
    $apiKeyForLists = isset($conf->global->MAIN_BREVOINTEGRATION_APIKEY) ? trim($conf->global->MAIN_BREVOINTEGRATION_APIKEY) : '';
    if ($apiKeyForLists === '') {
        $categoryListError = $langs->trans('BrevoMissingApiKey');
    } else {
        $listsApi = new BrevoApi($db, $conf, $apiKeyForLists);
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
        <input type="hidden" name="action" value="setapikey" />
        <input type="hidden" name="tab" value="configuration" />
        <table class="noborder" width="100%">
            <tr class="liste_titre">
                <th><?php echo $langs->trans('Parameter'); ?></th>
                <th><?php echo $langs->trans('Value'); ?></th>
            </tr>
            <tr>
                <td class="fieldrequired"><?php echo $langs->trans('BrevoApiKeyLabel'); ?></td>
                <td>
                    <input type="text" name="BREVOINTEGRATION_APIKEY" size="60" value="<?php echo dol_escape_htmltag(isset($conf->global->MAIN_BREVOINTEGRATION_APIKEY) ? $conf->global->MAIN_BREVOINTEGRATION_APIKEY : ''); ?>" />
                </td>
            </tr>
        </table>
        <div class="center">
            <input type="submit" class="button" value="<?php echo dol_escape_htmltag($langs->trans('Save')); ?>" />
        </div>
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
    dol_include_once('/brevointegration/core/modules/modBrevoIntegration.class.php');
    $moduleDescriptor = new modBrevoIntegration($db);
    $moduleVersion = (string) $moduleDescriptor->version;

    $detectedDolVersion = defined('DOL_VERSION') ? (string) DOL_VERSION : '';
    $normalizedDolVersion = preg_match('/^[0-9.]+$/', $detectedDolVersion) ? $detectedDolVersion : '0.0.0';
    $displayDolVersion = $detectedDolVersion !== '' ? $detectedDolVersion : $langs->trans('BrevoDiagnosticUnknownValue');
    $minimumDolVersion = '21.0.0';
    $phpVersion = PHP_VERSION;
    $environmentChecks = array();
    $environmentChecks[] = array(
        'label' => $langs->trans('BrevoDiagnosticVersionLabel'),
        'status' => 'ok',
        'details' => $langs->trans('BrevoDiagnosticVersionDetails', dol_escape_htmltag($moduleVersion))
    );
    $environmentChecks[] = array(
        'label' => $langs->trans('BrevoDiagnosticDolibarrVersion'),
        'status' => version_compare($normalizedDolVersion, $minimumDolVersion, '>=') ? 'ok' : 'warning',
        'details' => $langs->trans('BrevoDiagnosticDolibarrVersionDetails', dol_escape_htmltag($displayDolVersion), $minimumDolVersion)
    );
    $environmentChecks[] = array(
        'label' => $langs->trans('BrevoDiagnosticPhpVersion'),
        'status' => version_compare($phpVersion, '7.4.0', '>=') ? 'ok' : 'error',
        'details' => $langs->trans('BrevoDiagnosticPhpVersionDetails', dol_escape_htmltag($phpVersion))
    );
    $moduleEnabled = isset($conf->brevointegration->enabled) && (bool) $conf->brevointegration->enabled;
    $environmentChecks[] = array(
        'label' => $langs->trans('BrevoDiagnosticModuleEnabled'),
        'status' => $moduleEnabled ? 'ok' : 'error',
        'details' => $moduleEnabled ? $langs->trans('BrevoDiagnosticModuleEnabledDetailsEnabled') : $langs->trans('BrevoDiagnosticModuleEnabledDetailsDisabled')
    );
    $curlLoaded = function_exists('curl_init');
    $environmentChecks[] = array(
        'label' => $langs->trans('BrevoDiagnosticCurlExtension'),
        'status' => $curlLoaded ? 'ok' : 'error',
        'details' => $curlLoaded ? $langs->trans('BrevoDiagnosticCurlExtensionDetailsEnabled') : $langs->trans('BrevoDiagnosticCurlExtensionDetailsMissing')
    );

    $apiKey = isset($conf->global->MAIN_BREVOINTEGRATION_APIKEY) ? trim((string) $conf->global->MAIN_BREVOINTEGRATION_APIKEY) : '';
    $apiChecks = array();
    if ($apiKey !== '') {
        $maskedApiKey = str_repeat('*', max(strlen($apiKey) - 4, 0)).substr($apiKey, -4);
        $apiChecks[] = array(
            'label' => $langs->trans('BrevoDiagnosticApiKeyPresence'),
            'status' => 'ok',
            'details' => $langs->trans('BrevoDiagnosticApiKeyPresenceDetailsOk', dol_escape_htmltag($maskedApiKey))
        );
    } else {
        $apiChecks[] = array(
            'label' => $langs->trans('BrevoDiagnosticApiKeyPresence'),
            'status' => 'warning',
            'details' => $langs->trans('BrevoDiagnosticApiKeyPresenceDetailsMissing')
        );
    }

    $apiValidationStatus = 'warning';
    $apiValidationDetails = $langs->trans('BrevoDiagnosticApiKeyValidationDetailsMissingKey');
    if ($apiKey !== '') {
        if (!$curlLoaded) {
            $apiValidationStatus = 'error';
            $apiValidationDetails = $langs->trans('BrevoDiagnosticApiKeyValidationDetailsNoCurl');
        } else {
            try {
                $diagnosticApi = new BrevoApi($db, $conf, $apiKey);
                $validation = $diagnosticApi->validateApiKey($apiKey);
            } catch (Exception $exception) {
                $validation = array('success' => false, 'error' => $exception->getMessage());
            }

            if (!empty($validation['success'])) {
                $apiValidationStatus = 'ok';
                $apiValidationDetails = $langs->trans('BrevoDiagnosticApiKeyValidationDetailsOk');
            } else {
                $errorMessage = isset($validation['error']) ? (string) $validation['error'] : '';
                if ($errorMessage === '') {
                    $errorMessage = $langs->trans('Error');
                }
                $apiValidationStatus = 'error';
                $apiValidationDetails = $langs->trans('BrevoDiagnosticApiKeyValidationDetailsKo', dol_escape_htmltag($errorMessage));
            }
        }
    }

    $apiChecks[] = array(
        'label' => $langs->trans('BrevoDiagnosticApiKeyValidation'),
        'status' => $apiValidationStatus,
        'details' => $apiValidationDetails
    );

    $logService = new BrevoLogService($db, $conf);
    $logStorageStatus = $logService->getLogStorageStatus();
    $logChecks = array();
    $tableName = isset($logStorageStatus['table_name']) ? (string) $logStorageStatus['table_name'] : 'llx_brevo_log';
    $logChecks[] = array(
        'label' => $langs->trans('BrevoDiagnosticLogStorage'),
        'status' => !empty($logStorageStatus['exists']) ? 'ok' : 'error',
        'details' => !empty($logStorageStatus['exists']) ? $langs->trans('BrevoDiagnosticLogStorageDetailsOk', dol_escape_htmltag($tableName)) : $langs->trans('BrevoDiagnosticLogStorageDetailsMissing', dol_escape_htmltag($tableName))
    );
    $missingColumns = isset($logStorageStatus['missing_columns']) ? $logStorageStatus['missing_columns'] : array();
    $missingColumnsText = !empty($missingColumns) ? dol_escape_htmltag(implode(', ', $missingColumns)) : $langs->trans('BrevoDiagnosticLogStorageColumnsUnknown');
    $logChecks[] = array(
        'label' => $langs->trans('BrevoDiagnosticLogStorageColumns'),
        'status' => !empty($logStorageStatus['ready']) ? 'ok' : 'error',
        'details' => !empty($logStorageStatus['ready']) ? $langs->trans('BrevoDiagnosticLogStorageColumnsDetailsOk') : $langs->trans('BrevoDiagnosticLogStorageColumnsDetailsMissing', $missingColumnsText)
    );
    $logFile = __DIR__.'/logs.php';
    $logFileReadable = is_file($logFile) && is_readable($logFile);
    $logChecks[] = array(
        'label' => $langs->trans('BrevoDiagnosticLogPageAccess'),
        'status' => $logFileReadable ? 'ok' : 'error',
        'details' => $logFileReadable ? $langs->trans('BrevoDiagnosticLogPageAccessDetailsOk') : $langs->trans('BrevoDiagnosticLogPageAccessDetailsMissing')
    );

    print '<div class="fichecenter">';
    print '    <p class="opacitymedium">'.$langs->trans('BrevoDiagnosticIntro').'</p>';
    brevointegration_render_diagnostic_table($langs->trans('BrevoDiagnosticSectionEnvironment'), $environmentChecks, $langs);
    brevointegration_render_diagnostic_table($langs->trans('BrevoDiagnosticSectionApi'), $apiChecks, $langs);
    brevointegration_render_diagnostic_table($langs->trans('BrevoDiagnosticSectionLogs'), $logChecks, $langs);
    print '</div>';
}

dol_fiche_end();

llxFooter();
$db->close();
