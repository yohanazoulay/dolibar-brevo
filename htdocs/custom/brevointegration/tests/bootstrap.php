<?php
declare(strict_types=1);

$stubRoot = __DIR__.'/stubs';
if (!defined('DOL_DOCUMENT_ROOT')) {
    define('DOL_DOCUMENT_ROOT', $stubRoot);
}
if (!defined('DOL_URL_ROOT')) {
    define('DOL_URL_ROOT', '');
}

require_once $stubRoot.'/core/lib/functions.lib.php';
require_once $stubRoot.'/core/lib/date.lib.php';

spl_autoload_register(static function ($class) {
    if ($class === 'DoliDB') {
        eval('class DoliDB {}');
    }
});
