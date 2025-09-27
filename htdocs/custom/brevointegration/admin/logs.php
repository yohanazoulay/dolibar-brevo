<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Administration page to inspect Brevo API logs.
 */

// ---- Robust loader for main.inc.php (works with SaaS aliases) ----
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
if (!$res && !empty($_SERVER['DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
}
if (!$res && !empty($_SERVER['SCRIPT_FILENAME'])) {
    $tmp = dirname($_SERVER['SCRIPT_FILENAME']);
    for ($i = 0; $i < 10 && !$res; $i++) {
        if (file_exists($tmp.'/main.inc.php')) {
            $res = @include $tmp.'/main.inc.php';
        }
        $tmp = dirname($tmp);
    }
}
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
    $res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res) {
    http_response_code(500);
    die('Failed to include Dolibarr main.inc.php');
}
// ------------------------------------------------------------------
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/list.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/brevointegration/class/services/brevologservice.class.php');

/**
 * Build a timestamp using Dolibarr helper when available, otherwise fallback to PHP's mktime.
 *
 * @param int|string $hour   Hour component
 * @param int|string $minute Minute component
 * @param int|string $second Second component
 * @param int|string $month  Month component
 * @param int|string $day    Day component
 * @param int|string $year   Year component
 * @return int
 */
function brevointegrationBuildTimestamp($hour, $minute, $second, $month, $day, $year)
{
    $hour = (int) $hour;
    $minute = (int) $minute;
    $second = (int) $second;
    $month = (int) $month;
    $day = (int) $day;
    $year = (int) $year;

    if (function_exists('dol_mktime')) {
        return (int) dol_mktime($hour, $minute, $second, $month, $day, $year);
    }

    return (int) mktime($hour, $minute, $second, $month, $day, $year);
}

global $langs, $user, $conf, $db;

if (!$user->admin) {
    accessforbidden();
}

$langs->load('admin');
$langs->load('brevointegration@brevointegration');

$form = new Form($db);
$logService = new BrevoLogService($db, $conf);

$now = dol_now();
$defaultStart = $now - (7 * 24 * 3600);
$defaultEnd = $now;

$resetFilter = GETPOST('button_removefilter', 'alpha');

$startTimestamp = $defaultStart;
$endTimestamp = $defaultEnd;

if (!$resetFilter) {
    $startTimestamp = brevointegrationBuildTimestamp(
        0,
        0,
        0,
        GETPOST('filter_startmonth', 'int'),
        GETPOST('filter_startday', 'int'),
        GETPOST('filter_startyear', 'int')
    );
    $endTimestamp = brevointegrationBuildTimestamp(
        23,
        59,
        59,
        GETPOST('filter_endmonth', 'int'),
        GETPOST('filter_endday', 'int'),
        GETPOST('filter_endyear', 'int')
    );

    if ($startTimestamp <= 0) {
        $startTimestamp = $defaultStart;
    }
    if ($endTimestamp <= 0) {
        $endTimestamp = $defaultEnd;
    }
}

$limit = GETPOST('limit', 'int');
if ($limit <= 0) {
    $limit = isset($conf->liste_limit) ? (int) $conf->liste_limit : 25;
}

$page = GETPOST('page', 'int');
if ($page === '' || $page < 0) {
    $page = 0;
}
$offset = $limit * $page;

$sortfield = GETPOST('sortfield', 'aZ09');
$sortorder = GETPOST('sortorder', 'aZ09');

$resultSet = $logService->fetchLogs($startTimestamp, $endTimestamp, $limit, $offset, $sortfield, $sortorder);
$logs = $resultSet['logs'];
$total = $resultSet['total'];

$param = '';
if ($startTimestamp) {
    $param .= '&filter_startday='.date('d', $startTimestamp);
    $param .= '&filter_startmonth='.date('m', $startTimestamp);
    $param .= '&filter_startyear='.date('Y', $startTimestamp);
}
if ($endTimestamp) {
    $param .= '&filter_endday='.date('d', $endTimestamp);
    $param .= '&filter_endmonth='.date('m', $endTimestamp);
    $param .= '&filter_endyear='.date('Y', $endTimestamp);
}
if ($limit) {
    $param .= '&limit='.$limit;
}

llxHeader('', $langs->trans('BrevoLogsTitle'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('BrevoLogsTitle'), $linkback, 'icon-picto-brevo.svg@brevointegration');
print '<p class="opacitymedium">'.$langs->trans('BrevoLogsIntro').'</p>';

print '<form method="GET" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" class="filter">';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('BrevoLogsFilterPeriod').'</th>';
print '<th class="right">'.$langs->trans('Actions').'</th>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>';
print '<div class="inline-block">';
print '<label class="marginrightonly" for="filter_startday">'.$langs->trans('BrevoLogsFilterStart').'</label>';
print $form->selectDate($startTimestamp, 'filter_start', 0, 0, 1, '', 1, 1);
print '</div>';
print '<div class="inline-block margintoponly">';
print '<label class="marginrightonly" for="filter_endday">'.$langs->trans('BrevoLogsFilterEnd').'</label>';
print $form->selectDate($endTimestamp, 'filter_end', 0, 0, 1, '', 1, 1);
print '</div>';
print '</td>';
print '<td class="right">';
print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('BrevoLogsFilterSubmit')).'" />';
print ' ';
print '<input type="submit" class="button button-cancel" name="button_removefilter" value="'.dol_escape_htmltag($langs->trans('Reset')).'" />';
print '</td>';
print '</tr>';
print '</table>';
print '</form>';

$sortfield = in_array($sortfield, array('date_event', 'method', 'http_code', 'duration_ms', 'success'), true) ? $sortfield : 'date_event';
$sortorder = strtoupper($sortorder) === 'ASC' ? 'ASC' : 'DESC';

print_barre_liste(
    $langs->trans('BrevoLogsListTitle'),
    $page,
    $_SERVER['PHP_SELF'],
    $param,
    $sortfield,
    $sortorder,
    '',
    $total,
    $limit
);

print '<div class="div-table-responsive">';
print '<table class="noborder tagtable liste">';
print '<tr class="liste_titre">';
print_liste_field_titre($langs->trans('BrevoLogsDate'), $_SERVER['PHP_SELF'], 'date_event', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('BrevoLogsMethod'), $_SERVER['PHP_SELF'], 'method', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('BrevoLogsEndpoint'), $_SERVER['PHP_SELF'], 'endpoint', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans('BrevoLogsStatus'), $_SERVER['PHP_SELF'], 'http_code', '', $param, '', $sortfield, $sortorder, 'right');
print_liste_field_titre($langs->trans('BrevoLogsDuration'), $_SERVER['PHP_SELF'], 'duration_ms', '', $param, '', $sortfield, $sortorder, 'right');
print '<th class="left">'.$langs->trans('BrevoLogsMessage').'</th>';
print '</tr>';

if (empty($logs)) {
    $colspan = 6;
    print '<tr class="oddeven">';
    print '<td class="center" colspan="'.$colspan.'">'.$langs->trans('BrevoLogsEmpty').'</td>';
    print '</tr>';
} else {
    foreach ($logs as $log) {
        $class = empty($log['success']) ? 'error' : '';
        print '<tr class="oddeven'.($class !== '' ? ' '.$class : '').'">';
        print '<td>'.dol_print_date($log['date_event'], 'dayhour').'</td>';
        print '<td>'.dol_escape_htmltag($log['method']).'</td>';
        print '<td>'.dol_escape_htmltag($log['endpoint']).'</td>';
        print '<td class="right">'.dol_escape_htmltag((string) $log['http_code']).'</td>';
        $durationLabel = sprintf($langs->trans('BrevoLogsDurationUnit'), (int) $log['duration_ms']);
        print '<td class="right">'.dol_escape_htmltag($durationLabel).'</td>';
        $message = !empty($log['message']) ? dol_escape_htmltag($log['message']) : '&nbsp;';
        print '<td>'.$message.'</td>';
        print '</tr>';
    }
}

print '</table>';
print '</div>';

llxFooter();
$db->close();
