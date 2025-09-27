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

if (!$user->admin) {
    accessforbidden();
}

$langs->load('admin');
$langs->load('brevointegration@brevointegration');

$action = GETPOST('action', 'alpha');
$mappingService = new BrevoFieldMappingService($db, $conf);

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
            setEventMessages($response['error'], null, 'errors');
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
}

$helpUrl = '';
llxHeader('', $langs->trans('BrevoSetupTitle'), $helpUrl);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('BrevoSetupTitle'), $linkback, 'brevointegration@brevointegration');

$token = newToken();
$mappingToken = newToken();
$contactMapping = $mappingService->getMappingForType('contact');
$thirdpartyMapping = $mappingService->getMappingForType('thirdparty');
$contactMapping[] = array('attribute' => '', 'source' => '', 'field' => '');
$thirdpartyMapping[] = array('attribute' => '', 'source' => '', 'field' => '');
$contactFields = $mappingService->getAvailableFields('contact');
$thirdpartyFields = $mappingService->getAvailableFields('thirdparty');
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
<?php
llxFooter();
$db->close();
