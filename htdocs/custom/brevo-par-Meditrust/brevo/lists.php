<?php
declare(strict_types=1);

/**
 * @package   brevo-par-Meditrust
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Page to display Brevo contact lists.
 */

require '../../../main.inc.php';
dol_include_once('/brevo-par-Meditrust/class/brevoapi.class.php');

global $langs, $db, $conf, $user;

if (empty($conf->brevo->enabled)) {
    accessforbidden();
}
if (empty($user->rights->brevo->read)) {
    accessforbidden();
}

$langs->load('brevo@brevo-par-Meditrust');
$langs->load('other');

$limit = GETPOST('limit', 'int');
if ($limit <= 0) {
    $limit = 25;
}
$page = GETPOST('page', 'int');
if ($page < 0) {
    $page = 0;
}
$offset = $page * $limit;

$lists = array();
$total = 0;
$error = '';

$apiKey = isset($conf->global->MAIN_BREVO_APIKEY) ? $conf->global->MAIN_BREVO_APIKEY : '';
if ($apiKey === '') {
    $error = $langs->trans('BrevoMissingApiKey');
} else {
    $api = new BrevoApi($db, $conf, $apiKey);
    $response = $api->getLists($limit, $offset);
    if (!empty($response['success'])) {
        $lists = isset($response['data']['lists']) ? $response['data']['lists'] : array();
        $total = isset($response['data']['count']) ? (int) $response['data']['count'] : count($lists);
    } else {
        $error = $response['error'];
    }
}

$title = $langs->trans('BrevoListsTitle');
llxHeader('', $title);

print load_fiche_titre($title, '', 'brevo@brevo-par-Meditrust');

if ($error !== '') {
    print '<div class="error">'.dol_escape_htmltag($error).'</div>';
} else {
    print '<div class="div-table-responsive">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>'.$langs->trans('BrevoListId').'</th>';
    print '<th>'.$langs->trans('Label').'</th>';
    print '<th>'.$langs->trans('BrevoTotalContacts').'</th>';
    print '</tr>';

    if (empty($lists)) {
        print '<tr><td colspan="3" class="opacitymedium">'.$langs->trans('NoDataFound').'</td></tr>';
    } else {
        foreach ($lists as $list) {
            $listId = isset($list['id']) ? (int) $list['id'] : 0;
            $listName = isset($list['name']) ? $list['name'] : '';
            $listTotal = isset($list['totalSubscribers']) ? (int) $list['totalSubscribers'] : 0;
            print '<tr>';
            print '<td>'.$listId.'</td>';
            print '<td>'.dol_escape_htmltag($listName).'</td>';
            print '<td>'.$listTotal.'</td>';
            print '</tr>';
        }
    }
    print '</table>';
    print '</div>';

    $param = '&limit='.$limit;
    print '<div class="pagination">';
    if ($page > 0) {
        print '<a class="butAction" href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?page='.($page - 1).$param.'">'.$langs->trans('Previous').'</a> ';
    }
    if (($offset + $limit) < $total) {
        print '<a class="butAction" href="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'?page='.($page + 1).$param.'">'.$langs->trans('Next').'</a>';
    }
    print '</div>';
}

llxFooter();
$db->close();
