<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Administration page to inspect Brevo API logs.
 */

// --- Deterministic loader (more reliable on SaaS/proxied setups)
require __DIR__.'/../../../main.inc.php';
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
$storageStatus = $logService->getLogStorageStatus();
$storageReady = !empty($storageStatus['exists']) && !empty($storageStatus['ready']);

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

$limitInput = (int) GETPOST('limit', 'int');
if ($limitInput <= 0) {
    $limitInput = isset($conf->liste_limit) ? (int) $conf->liste_limit : 50;
}
$limit = max(10, min(100, $limitInput));

$page = GETPOST('page', 'int');
if ($page === '' || $page < 0) {
    $page = 0;
}
$offset = $limit * $page;

$allowedSortfields = array(
    'date_event' => 'date_event',
    'method' => 'method',
    'http_code' => 'http_code',
    'duration_ms' => 'duration_ms',
    'success' => 'success',
);
$sortfield = GETPOST('sortfield', 'aZ09');
if (!isset($allowedSortfields[$sortfield])) {
    $sortfield = 'date_event';
}
$sortorder = GETPOST('sortorder', 'alpha');
$sortorder = strtolower((string) $sortorder) === 'asc' ? 'ASC' : 'DESC';

$logs = array();
$total = 0;
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

if (!$storageStatus['exists']) {
    setEventMessages($langs->trans('BrevoLogsStorageMissingTable', dol_escape_htmltag($storageStatus['table_name'])), null, 'warnings');
} elseif (!$storageStatus['ready']) {
    $missingColumns = array();
    if (!empty($storageStatus['missing_columns'])) {
        if (is_array($storageStatus['missing_columns'])) {
            $missingColumns = array_map('dol_escape_htmltag', $storageStatus['missing_columns']);
        } else {
            dol_syslog(__FILE__.' unexpected missing_columns payload type: '.gettype($storageStatus['missing_columns']), LOG_ERR);
            $missingColumns = array(dol_escape_htmltag((string) $storageStatus['missing_columns']));
        }
    }
    $missingList = !empty($missingColumns) ? implode(', ', $missingColumns) : dol_escape_htmltag($langs->trans('Unknown'));
    setEventMessages($langs->trans('BrevoLogsStorageMissingColumns', $missingList), null, 'warnings');
} else {
    $conditions = array('entity = '.((int) $conf->entity));

    if ($startTimestamp > 0) {
        $conditions[] = 'date_event >= '.(method_exists($db, 'idate') ? $db->idate($startTimestamp) : "'".date('Y-m-d H:i:s', (int) $startTimestamp)."'");
    }
    if ($endTimestamp > 0) {
        $conditions[] = 'date_event <= '.(method_exists($db, 'idate') ? $db->idate($endTimestamp) : "'".date('Y-m-d H:i:s', (int) $endTimestamp)."'");
    }

    $whereClause = implode(' AND ', $conditions);

    $countSql = 'SELECT COUNT(*) as total FROM '.MAIN_DB_PREFIX.'brevo_log WHERE '.$whereClause;
    $resCount = $db->query($countSql);
    if ($resCount === false) {
        dol_syslog(__FILE__.'::count_logs '.$db->lasterror(), LOG_ERR);
        setEventMessages($langs->trans('ErrorInternalError'), null, 'errors');
    } else {
        $countObj = $db->fetch_object($resCount);
        $total = $countObj ? (int) $countObj->total : 0;
        if (method_exists($db, 'free')) {
            $db->free($resCount);
        }

        $sql = 'SELECT rowid, date_event, method, endpoint, http_code, duration_ms, success, message FROM '.MAIN_DB_PREFIX.'brevo_log';
        $sql .= ' WHERE '.$whereClause;
        $sql .= ' ORDER BY '.$allowedSortfields[$sortfield].' '.$sortorder;
        $sql .= $db->plimit($limit, $offset);

        $resql = $db->query($sql);
        if ($resql === false) {
            dol_syslog(__FILE__.'::select_logs '.$db->lasterror(), LOG_ERR);
            setEventMessages($langs->trans('ErrorInternalError'), null, 'errors');
        } else {
            while ($obj = $db->fetch_object($resql)) {
                $logs[] = array(
                    'rowid' => isset($obj->rowid) ? (int) $obj->rowid : 0,
                    'date_event' => isset($obj->date_event) ? (int) (method_exists($db, 'jdate') ? $db->jdate($obj->date_event) : strtotime((string) $obj->date_event)) : 0,
                    'method' => isset($obj->method) ? (string) $obj->method : '',
                    'endpoint' => isset($obj->endpoint) ? (string) $obj->endpoint : '',
                    'http_code' => isset($obj->http_code) ? (int) $obj->http_code : 0,
                    'duration_ms' => isset($obj->duration_ms) ? (int) $obj->duration_ms : 0,
                    'success' => !empty($obj->success) ? 1 : 0,
                    'message' => isset($obj->message) ? (string) $obj->message : '',
                );
            }
            if (method_exists($db, 'free')) {
                $db->free($resql);
            }
        }
    }
}

llxHeader('', $langs->trans('BrevoLogsTitle'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('BrevoLogsTitle'), $linkback, 'icon-picto-brevo.svg@brevointegration');
print '<p class="opacitymedium">'.$langs->trans('BrevoLogsIntro').'</p>';

if ($storageReady) {
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
            $message = $log['message'] !== '' ? dol_escape_htmltag($log['message']) : '&nbsp;';
            print '<td>'.$message.'</td>';
            print '</tr>';
        }
    }

    print '</table>';
    print '</div>';
} else {
    print '<div class="opacitymedium">'.$langs->trans('BrevoNoLogs').'</div>';
}

llxFooter();
$db->close();
