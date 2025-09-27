<?php
declare(strict_types=1);

if (!defined('LOG_DEBUG')) {
    define('LOG_DEBUG', 7);
}
if (!defined('LOG_WARNING')) {
    define('LOG_WARNING', 4);
}

function dol_syslog($message, $level = LOG_DEBUG)
{
    // no-op in unit tests
}

function dol_escape_htmltag($string)
{
    return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

function dol_buildpath($path)
{
    $fullPath = DOL_DOCUMENT_ROOT.$path;
    if (!file_exists($fullPath)) {
        $moduleRoot = dirname(__DIR__, 4);
        $alternative = $moduleRoot.$path;
        if (file_exists($alternative)) {
            return $alternative;
        }
    }

    return $fullPath;
}

function newToken()
{
    return 'testtoken';
}

function checkToken()
{
    return true;
}

function setEventMessages($mesg, $mesgs = null, $style = 'mesgs')
{
    // no-op for tests
}

if (!function_exists('dol_include_once')) {
    function dol_include_once($path)
    {
        $fullPath = DOL_DOCUMENT_ROOT.$path;
        if (substr($fullPath, -4) !== '.php') {
            $fullPath .= '.php';
        }
        if (!file_exists($fullPath)) {
            $moduleRoot = dirname(__DIR__, 4);
            $alternatives = array(
                $moduleRoot.$path,
                dirname($moduleRoot).$path,
            );
            foreach ($alternatives as $alt) {
                if (substr($alt, -4) !== '.php') {
                    $alt .= '.php';
                }
                if (file_exists($alt)) {
                    require_once $alt;

                    return;
                }
            }
        }
        if (file_exists($fullPath)) {
            require_once $fullPath;
        }
    }
}

if (!function_exists('dolibarr_set_const')) {
    function dolibarr_set_const($db, $name, $value, $type = 'chaine', $note = 0, $desc = '', $entity = 1)
    {
        if (!isset($GLOBALS['dolibarr_const'])) {
            $GLOBALS['dolibarr_const'] = array();
        }
        if (!isset($GLOBALS['dolibarr_const'][$entity])) {
            $GLOBALS['dolibarr_const'][$entity] = array();
        }

        $GLOBALS['dolibarr_const'][$entity][$name] = $value;

        return 1;
    }
}

if (!function_exists('dolibarr_del_const')) {
    function dolibarr_del_const($db, $name, $entity = 1)
    {
        if (isset($GLOBALS['dolibarr_const'][$entity][$name])) {
            unset($GLOBALS['dolibarr_const'][$entity][$name]);

            return 1;
        }

        return 0;
    }
}
