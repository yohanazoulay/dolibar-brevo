<?php
declare(strict_types=1);

/**
 * @package   brevo-par-Meditrust
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Administration page to configure Brevo API key.
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/brevo-par-Meditrust/class/brevoapi.class.php');

global $langs, $user, $conf, $db;

if (!$user->admin) {
    accessforbidden();
}

$langs->load('admin');
$langs->load('brevo@brevo-par-Meditrust');

$action = GETPOST('action', 'alpha');

if ($action === 'setapikey') {
    if (!checkToken()) {
        accessforbidden();
    }

    $apiKey = trim(GETPOST('BREVO_APIKEY', 'restricthtml'));
    if ($apiKey === '') {
        dolibarr_del_const($db, 'MAIN_BREVO_APIKEY', $conf->entity);
        setEventMessages($langs->trans('BrevoApiKeyRemoved'), null, 'mesgs');
    } else {
        $api = new BrevoApi($db, $conf, $apiKey);
        $response = $api->validateApiKey($apiKey);
        if (!empty($response['success'])) {
            dolibarr_set_const($db, 'MAIN_BREVO_APIKEY', $apiKey, 'chaine', 0, '', $conf->entity);
            setEventMessages($langs->trans('BrevoApiKeySaved'), null, 'mesgs');
        } else {
            setEventMessages($response['error'], null, 'errors');
        }
    }
}

$helpUrl = '';
llxHeader('', $langs->trans('BrevoSetupTitle'), $helpUrl);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('BrevoSetupTitle'), $linkback, 'brevo@brevo-par-Meditrust');

$token = newToken();
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
                <input type="text" name="BREVO_APIKEY" size="60" value="<?php echo dol_escape_htmltag(isset($conf->global->MAIN_BREVO_APIKEY) ? $conf->global->MAIN_BREVO_APIKEY : ''); ?>" />
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
