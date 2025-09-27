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

global $langs, $user, $conf, $db;

if (!$user->admin) {
    accessforbidden();
}

$langs->load('admin');
$langs->load('brevointegration@brevointegration');

$action = GETPOST('action', 'alpha');

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
}

$helpUrl = '';
llxHeader('', $langs->trans('BrevoSetupTitle'), $helpUrl);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('BrevoSetupTitle'), $linkback, 'brevointegration@brevointegration');

$token = newToken();
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
<?php
llxFooter();
$db->close();
