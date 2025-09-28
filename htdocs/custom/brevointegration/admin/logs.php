<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Administration page to inspect Brevo API logs.
 */

// --- Deterministic loader compatible with module installed in htdocs/ or htdocs/custom/
if (!defined('DOL_DOCUMENT_ROOT')) {
    $mainIncludeFound = false;
    $includeCandidates = array(
        __DIR__.'/../../main.inc.php',
        __DIR__.'/../../master.inc.php',
        __DIR__.'/../../../main.inc.php',
        __DIR__.'/../../../master.inc.php',
    );

    foreach ($includeCandidates as $includeCandidate) {
        if (!is_file($includeCandidate)) {
            continue;
        }

        require_once $includeCandidate;

        if (defined('DOL_DOCUMENT_ROOT')) {
            $mainIncludeFound = true;
            break;
        }
    }

    if (!$mainIncludeFound) {
        throw new RuntimeException('Unable to load Dolibarr main include file.');
    }
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/brevointegration/class/services/brevologservice.class.php');
dol_include_once('/brevointegration/lib/brevointegration_date.lib.php');
dol_include_once('/brevointegration/lib/brevointegration_logger.lib.php');

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

/**
 * Attempt to load Dolibarr list helper functions and expose their availability.
 *
 * @return array{available:bool,source:string}
 */
function brevointegrationLoadListHelpers()
{
    static $result = null;

    if ($result !== null) {
        return $result;
    }

    $result = array('available' => false, 'source' => 'none');
    $candidates = array(
        'list.lib.php' => DOL_DOCUMENT_ROOT.'/core/lib/list.lib.php',
        'functions2.lib.php' => DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php',
    );

    foreach ($candidates as $source => $path) {
        if (!is_file($path)) {
            continue;
        }

        require_once $path;

        if (function_exists('print_barre_liste') && function_exists('print_liste_field_titre')) {
            $result = array('available' => true, 'source' => $source);

            return $result;
        }
    }

    if (function_exists('print_barre_liste') && function_exists('print_liste_field_titre')) {
        $result = array('available' => true, 'source' => 'preloaded');

        return $result;
    }

    return $result;
}

/**
 * Build a query string from associative parameters while preserving zero values.
 *
 * @param array<string,int|string> $params
 * @return string
 */
function brevointegrationBuildQueryString(array $params)
{
    $parts = array();

    foreach ($params as $key => $value) {
        if ($value === null) {
            continue;
        }

        $stringValue = (string) $value;
        if ($stringValue === '' && $value !== 0 && $value !== '0') {
            continue;
        }

        $parts[] = rawurlencode((string) $key).'='.rawurlencode($stringValue);
    }

    return implode('&', $parts);
}

/**
 * Render a minimalistic pagination header when Dolibarr list helpers are unavailable.
 *
 * @param string                   $title
 * @param int                      $page
 * @param int                      $limit
 * @param int                      $total
 * @param string                   $selfUrl
 * @param array<string,int|string> $baseParams
 * @return void
 */
function brevointegrationRenderFallbackListHeader($title, $page, $limit, $total, $selfUrl, array $baseParams)
{
    $start = 0;
    $end = 0;
    if ($total > 0 && $limit > 0) {
        $start = ($page * $limit) + 1;
        $end = min($total, ($page + 1) * $limit);
    } elseif ($total > 0) {
        $start = 1;
        $end = $total;
    }

    $totalPages = ($limit > 0 && $total > 0) ? (int) ceil($total / $limit) : ($total > 0 ? 1 : 0);

    print '<div class="liste_manual_header clearfix">';
    print '<div class="inline-block"><strong>'.dol_escape_htmltag($title).'</strong>';
    if ($total > 0) {
        $rangeLabel = $start > 0 ? sprintf('%d-%d / %d', $start, $end, $total) : (string) $total;
        print ' <span class="opacitymedium">'.dol_escape_htmltag($rangeLabel).'</span>';
    }
    print '</div>';

    print '<div class="inline-block floatright">';

    $prevDisabled = ($page <= 0);
    if ($prevDisabled) {
        print '<span class="button button-small disabled">&laquo;</span>';
    } else {
        $prevParams = array_merge($baseParams, array('page' => $page - 1));
        $prevQuery = brevointegrationBuildQueryString($prevParams);
        $prevUrl = dol_escape_htmltag($selfUrl).($prevQuery === '' ? '' : '?'.$prevQuery);
        print '<a class="button button-small" href="'.$prevUrl.'">&laquo;</a>';
    }

    $pageLabel = $totalPages > 0 ? ($page + 1).' / '.$totalPages : '1 / 1';
    print '<span class="button button-small disabled">'.dol_escape_htmltag($pageLabel).'</span>';

    $nextDisabled = ($totalPages === 0) || ($page >= $totalPages - 1);
    if ($nextDisabled) {
        print '<span class="button button-small disabled">&raquo;</span>';
    } else {
        $nextParams = array_merge($baseParams, array('page' => $page + 1));
        $nextQuery = brevointegrationBuildQueryString($nextParams);
        $nextUrl = dol_escape_htmltag($selfUrl).($nextQuery === '' ? '' : '?'.$nextQuery);
        print '<a class="button button-small" href="'.$nextUrl.'">&raquo;</a>';
    }

    print '</div>';
    print '</div>';
}

/**
 * Render list headers with manual sorting links when Dolibarr helpers are unavailable.
 *
 * @param array<int,array<string,string>> $columns
 * @param string                          $selfUrl
 * @param array<string,int|string>        $baseParams
 * @param string                          $sortfield
 * @param string                          $sortorder
 * @return void
 */
function brevointegrationRenderFallbackTableHeaders(array $columns, $selfUrl, array $baseParams, $sortfield, $sortorder)
{
    print '<tr class="liste_titre">';

    foreach ($columns as $column) {
        $align = isset($column['align']) && $column['align'] !== '' ? ' '.trim((string) $column['align']) : '';
        $field = isset($column['field']) ? (string) $column['field'] : '';
        $isCurrentSort = ($field !== '' && strtolower($field) === strtolower((string) $sortfield));
        $displayLabel = dol_escape_htmltag($column['label']);
        if ($isCurrentSort) {
            $displayLabel .= (strtoupper((string) $sortorder) === 'ASC') ? ' ▲' : ' ▼';
        }

        if ($field !== '') {
            $nextOrder = ($isCurrentSort && strtoupper((string) $sortorder) === 'ASC') ? 'DESC' : 'ASC';
            $params = array_merge($baseParams, array(
                'page' => 0,
                'sortfield' => $field,
                'sortorder' => $nextOrder,
            ));
            $query = brevointegrationBuildQueryString($params);
            $url = dol_escape_htmltag($selfUrl).($query === '' ? '' : '?'.$query);
            $displayLabel = '<a href="'.$url.'" class="nowraponall">'.$displayLabel.'</a>';
        }

        print '<th class="liste_titre'.$align.'">'.$displayLabel.'</th>';
    }

    print '</tr>';
}

global $langs, $user, $conf, $db;

$brevoLogsRequestId = uniqid('brevo_logs_', true);
$httpContext = array(
    'request_id' => $brevoLogsRequestId,
    'method' => isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'CLI',
    'uri' => isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : 'unknown',
    'remote_ip' => isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown',
);

$currentUserId = isset($user->id) ? (int) $user->id : 0;
$currentUserLogin = isset($user->login) ? (string) $user->login : '';

$startContext = $httpContext + array(
    'user_id' => $currentUserId,
    'user_login' => $currentUserLogin,
    'query' => isset($_GET) && is_array($_GET) ? $_GET : array(),
);

brevointegration_logger_info('logs.php started by user='.($currentUserLogin !== '' ? $currentUserLogin : (string) $currentUserId), $startContext);

if (!$user->admin) {
    brevointegration_logger_error('logs.php forbidden access', $httpContext + array('user_id' => $currentUserId, 'user_login' => $currentUserLogin));
    accessforbidden();
}

$layoutStarted = false;
$pageStatus = 'success';
$logsFetched = 0;
$selfUrl = isset($_SERVER['PHP_SELF']) ? (string) $_SERVER['PHP_SELF'] : '';

try {
    $langs->load('admin');
    $langs->load('brevointegration@brevointegration');

    $listHelpersInfo = brevointegrationLoadListHelpers();
    $listHelpersAvailable = !empty($listHelpersInfo['available']);
    $listHelpersSource = isset($listHelpersInfo['source']) ? (string) $listHelpersInfo['source'] : 'none';

    brevointegration_logger_debug('logs.php list helpers detection', $httpContext + array(
        'available' => $listHelpersAvailable,
        'source' => $listHelpersSource,
    ));

    if (!$listHelpersAvailable) {
        brevointegration_logger_info('logs.php list helpers unavailable, using fallback renderer', $httpContext + array(
            'self' => $selfUrl,
            'source' => $listHelpersSource,
        ));
        setEventMessages($langs->trans('BrevoLogsListFallbackNotice'), null, 'warnings');
    }

    $form = new Form($db);
    $logService = new BrevoLogService($db, $conf);

    $storageStatus = $logService->getLogStorageStatus();
    $storageReady = !empty($storageStatus['exists']) && !empty($storageStatus['ready']);

    brevointegration_logger_debug('logs.php storage status', $httpContext + array('status' => $storageStatus));

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
    $queryParams = array();

    if ($startTimestamp) {
        $param .= '&filter_startday='.date('d', $startTimestamp);
        $param .= '&filter_startmonth='.date('m', $startTimestamp);
        $param .= '&filter_startyear='.date('Y', $startTimestamp);
        $queryParams['filter_startday'] = date('d', $startTimestamp);
        $queryParams['filter_startmonth'] = date('m', $startTimestamp);
        $queryParams['filter_startyear'] = date('Y', $startTimestamp);
    }
    if ($endTimestamp) {
        $param .= '&filter_endday='.date('d', $endTimestamp);
        $param .= '&filter_endmonth='.date('m', $endTimestamp);
        $param .= '&filter_endyear='.date('Y', $endTimestamp);
        $queryParams['filter_endday'] = date('d', $endTimestamp);
        $queryParams['filter_endmonth'] = date('m', $endTimestamp);
        $queryParams['filter_endyear'] = date('Y', $endTimestamp);
    }
    if ($limit) {
        $param .= '&limit='.$limit;
        $queryParams['limit'] = $limit;
    }

    $queryParams['sortfield'] = $sortfield;
    $queryParams['sortorder'] = $sortorder;
    $queryParams['page'] = $page;

    if (!$storageStatus['exists']) {
        brevointegration_logger_error('logs.php missing table', $httpContext + array('status' => $storageStatus, 'self' => $selfUrl));
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
        brevointegration_logger_error('logs.php incomplete table schema', $httpContext + array('missing' => $missingColumns, 'self' => $selfUrl));
        setEventMessages($langs->trans('BrevoLogsStorageMissingColumns', $missingList), null, 'warnings');
    } else {
        $conditions = array('entity = '.((int) $conf->entity));

        if ($startTimestamp > 0) {
            $startSql = brevointegration_format_sql_datetime($db, $startTimestamp);
            if ($startSql !== null) {
                $conditions[] = 'date_event >= '.$startSql;
            }
        }
        if ($endTimestamp > 0) {
            $endSql = brevointegration_format_sql_datetime($db, $endTimestamp);
            if ($endSql !== null) {
                $conditions[] = 'date_event <= '.$endSql;
            }
        }

        $whereClause = implode(' AND ', $conditions);

        $countSql = 'SELECT COUNT(*) as total FROM '.MAIN_DB_PREFIX.'brevo_log WHERE '.$whereClause;
        brevointegration_logger_info('Executing SQL (count)', $httpContext + array('sql' => $countSql, 'self' => $selfUrl));
        $resCount = $db->query($countSql);
        if ($resCount === false) {
            $errorMessage = $db->lasterror();
            $pageStatus = 'sql_error';
            brevointegration_logger_error('logs.php count query failed', $httpContext + array('sql' => $countSql, 'error' => $errorMessage, 'self' => $selfUrl));
            dol_syslog(__FILE__.'::count_logs '.$errorMessage, LOG_ERR);
            setEventMessages($langs->trans('ErrorSQL'), null, 'errors');
        } else {
            $countObj = $db->fetch_object($resCount);
            $total = $countObj ? (int) $countObj->total : 0;
            if (method_exists($db, 'free')) {
                $db->free($resCount);
            }

            brevointegration_logger_info('logs.php count query fetched total', $httpContext + array('total' => $total, 'self' => $selfUrl));

            $sql = 'SELECT rowid, date_event, method, endpoint, http_code, duration_ms, success, message FROM '.MAIN_DB_PREFIX.'brevo_log';
            $sql .= ' WHERE '.$whereClause;
            $sql .= ' ORDER BY '.$allowedSortfields[$sortfield].' '.$sortorder;
            $sql .= $db->plimit($limit, $offset);

            brevointegration_logger_info('Executing SQL (select)', $httpContext + array('sql' => $sql, 'limit' => $limit, 'offset' => $offset, 'self' => $selfUrl));
            $resql = $db->query($sql);
            if ($resql === false) {
                $errorMessage = $db->lasterror();
                $pageStatus = 'sql_error';
                brevointegration_logger_error('logs.php select query failed', $httpContext + array('sql' => $sql, 'error' => $errorMessage, 'self' => $selfUrl));
                dol_syslog(__FILE__.'::select_logs '.$errorMessage, LOG_ERR);
                setEventMessages($langs->trans('ErrorSQL'), null, 'errors');
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

                $logsFetched = count($logs);
                brevointegration_logger_info('logs.php select query fetched rows', $httpContext + array('fetched' => $logsFetched, 'self' => $selfUrl));
            }
        }
    }

    llxHeader('', $langs->trans('BrevoLogsTitle'));
    $layoutStarted = true;

    $linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
    print load_fiche_titre($langs->trans('BrevoLogsTitle'), $linkback, 'icon-picto-brevo.svg@brevointegration');
    print '<p class="opacitymedium">'.$langs->trans('BrevoLogsIntro').'</p>';

    brevointegration_logger_info('logs.php rendering list', $httpContext + array(
        'storage_ready' => $storageReady,
        'total' => $total,
        'displayed' => count($logs),
        'page' => $page,
        'limit' => $limit,
        'sortfield' => $sortfield,
        'sortorder' => $sortorder,
        'list_helpers' => $listHelpersAvailable ? 'native' : 'fallback',
        'self' => $selfUrl,
    ));

    if ($storageReady) {
        print '<form method="GET" action="'.dol_escape_htmltag($selfUrl).'" class="filter">';
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

        if ($listHelpersAvailable) {
            print_barre_liste(
                $langs->trans('BrevoLogsListTitle'),
                $page,
                $selfUrl,
                $param,
                $sortfield,
                $sortorder,
                '',
                $total,
                $limit
            );
        } else {
            brevointegrationRenderFallbackListHeader(
                $langs->trans('BrevoLogsListTitle'),
                $page,
                $limit,
                $total,
                $selfUrl,
                $queryParams
            );
        }

        print '<div class="div-table-responsive">';
        print '<table class="noborder tagtable liste">';
        if ($listHelpersAvailable) {
            print '<tr class="liste_titre">';
            print_liste_field_titre($langs->trans('BrevoLogsDate'), $selfUrl, 'date_event', '', $param, '', $sortfield, $sortorder);
            print_liste_field_titre($langs->trans('BrevoLogsMethod'), $selfUrl, 'method', '', $param, '', $sortfield, $sortorder);
            print_liste_field_titre($langs->trans('BrevoLogsEndpoint'), $selfUrl, 'endpoint', '', $param, '', $sortfield, $sortorder);
            print_liste_field_titre($langs->trans('BrevoLogsStatus'), $selfUrl, 'http_code', '', $param, '', $sortfield, $sortorder, 'right');
            print_liste_field_titre($langs->trans('BrevoLogsDuration'), $selfUrl, 'duration_ms', '', $param, '', $sortfield, $sortorder, 'right');
            print '<th class="left">'.$langs->trans('BrevoLogsMessage').'</th>';
            print '</tr>';
        } else {
            brevointegrationRenderFallbackTableHeaders(
                array(
                    array('label' => $langs->trans('BrevoLogsDate'), 'field' => 'date_event', 'align' => ''),
                    array('label' => $langs->trans('BrevoLogsMethod'), 'field' => 'method', 'align' => ''),
                    array('label' => $langs->trans('BrevoLogsEndpoint'), 'field' => 'endpoint', 'align' => ''),
                    array('label' => $langs->trans('BrevoLogsStatus'), 'field' => 'http_code', 'align' => 'right'),
                    array('label' => $langs->trans('BrevoLogsDuration'), 'field' => 'duration_ms', 'align' => 'right'),
                    array('label' => $langs->trans('BrevoLogsMessage'), 'field' => '', 'align' => 'left'),
                ),
                $selfUrl,
                $queryParams,
                $sortfield,
                $sortorder
            );
        }

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
    $layoutStarted = false;

} catch (Throwable $exception) {
    $pageStatus = 'exception';
    brevointegration_logger_error('logs.php fatal exception', $httpContext + array('exception' => $exception, 'self' => $selfUrl));

    if (!$layoutStarted) {
        llxHeader('', $langs->trans('BrevoLogsTitle'));
        $layoutStarted = true;
        $linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans('BackToModuleList').'</a>';
        print load_fiche_titre($langs->trans('BrevoLogsTitle'), $linkback, 'icon-picto-brevo.svg@brevointegration');
    }

    setEventMessages($langs->trans('ErrorInternalError'), null, 'errors');
    print '<div class="error">'.dol_escape_htmltag($langs->trans('ErrorInternalError')).'</div>';

    if ($layoutStarted) {
        llxFooter();
    }
} finally {
    if (isset($db) && method_exists($db, 'close')) {
        $db->close();
    }

    brevointegration_logger_info(
        $pageStatus === 'success' ? 'logs.php finished normally' : 'logs.php finished with status='.$pageStatus,
        $httpContext + array(
            'status' => $pageStatus,
            'total' => isset($total) ? (int) $total : 0,
            'displayed' => $logsFetched,
            'self' => $selfUrl,
            'user_id' => $currentUserId,
            'user_login' => $currentUserLogin,
        )
    );
}
