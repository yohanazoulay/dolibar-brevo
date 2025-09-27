<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Page to display Brevo contact lists.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
if (!$res) {
    $tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
    $tmp2 = realpath(__FILE__);
    $i = strlen($tmp) - 1;
    $j = strlen($tmp2) - 1;
    while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] === $tmp2[$j]) {
        $i--;
        $j--;
    }
    if ($i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
        $res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
    }
    if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
        $res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
    }
}
if (!$res && file_exists('../main.inc.php')) {
    $res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
    $res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
    $res = @include '../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

dol_include_once('/brevointegration/class/brevoapi.class.php');

global $langs, $db, $conf, $user;

if (empty($conf->brevointegration->enabled)) {
    accessforbidden();
}
if (empty($user->rights->brevointegration->read)) {
    accessforbidden();
}

$langs->load('brevointegration@brevointegration');
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

$apiKey = isset($conf->global->MAIN_BREVOINTEGRATION_APIKEY) ? $conf->global->MAIN_BREVOINTEGRATION_APIKEY : '';
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

print load_fiche_titre($title, '', 'brevointegration@brevointegration');

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
