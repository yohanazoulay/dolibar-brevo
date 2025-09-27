<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Security helpers to ensure CSRF protection works even when Dolibarr token helpers are unavailable.
 */

if (!function_exists('brevointegration_require_security_libs')) {
    /**
     * Ensure Dolibarr security libraries are loaded when available.
     *
     * @return void
     */
    function brevointegration_require_security_libs(): void
    {
        if (!defined('DOL_DOCUMENT_ROOT')) {
            return;
        }

        if (!function_exists('newToken')) {
            $functionsPath = DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
            if (file_exists($functionsPath)) {
                require_once $functionsPath;
            } else {
                brevointegration_log_security_issue('Missing functions.lib.php to provide newToken().');
            }
        }

        if (!function_exists('checkToken')) {
            $securityPath = DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
            if (file_exists($securityPath)) {
                require_once $securityPath;
            } else {
                brevointegration_log_security_issue('Missing security.lib.php to provide checkToken().');
            }
        }
    }
}

if (!function_exists('brevointegration_log_security_issue')) {
    /**
     * Log a warning without triggering fatal errors when Dolibarr helpers are unavailable.
     *
     * @param string $message Message to log
     * @return void
     */
    function brevointegration_log_security_issue(string $message): void
    {
        if (function_exists('dol_syslog')) {
            $level = defined('LOG_WARNING') ? LOG_WARNING : 4;
            dol_syslog(__FILE__.' '.$message, $level);
        } else {
            error_log('[brevointegration] '.$message);
        }
    }
}

if (!function_exists('brevointegration_new_token')) {
    /**
     * Generate a CSRF token using Dolibarr helper when available, otherwise fallback to a local implementation.
     *
     * @return string
     */
    function brevointegration_new_token(): string
    {
        brevointegration_require_security_libs();

        if (function_exists('newToken')) {
            return (string) newToken();
        }

        brevointegration_log_security_issue('Fallback new token generator used because newToken() is unavailable.');

        $token = '';
        try {
            $token = bin2hex(random_bytes(16));
        } catch (Throwable $exception) {
            $token = sha1((string) mt_rand().microtime(true));
        }

        if (!isset($_SESSION)) {
            $_SESSION = array();
        }

        if (!isset($_SESSION['brevointegration_token_pool']) || !is_array($_SESSION['brevointegration_token_pool'])) {
            $_SESSION['brevointegration_token_pool'] = array();
        }

        $_SESSION['brevointegration_token_pool'][] = $token;
        if (count($_SESSION['brevointegration_token_pool']) > 20) {
            array_shift($_SESSION['brevointegration_token_pool']);
        }

        $_SESSION['newtoken'] = $token;

        return $token;
    }
}

if (!function_exists('brevointegration_check_token')) {
    /**
     * Validate a CSRF token using Dolibarr helper when available, otherwise fallback to a local implementation.
     *
     * @return bool
     */
    function brevointegration_check_token(): bool
    {
        brevointegration_require_security_libs();

        if (function_exists('checkToken')) {
            return (bool) checkToken();
        }

        brevointegration_log_security_issue('Fallback token validator used because checkToken() is unavailable.');

        $token = '';
        if (function_exists('GETPOST')) {
            $token = (string) GETPOST('token', 'alphanohtml');
        } elseif (isset($_POST['token'])) {
            $token = (string) $_POST['token'];
        } elseif (isset($_GET['token'])) {
            $token = (string) $_GET['token'];
        }

        if ($token === '') {
            return false;
        }

        $sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
        $pool = isset($_SESSION['brevointegration_token_pool']) && is_array($_SESSION['brevointegration_token_pool']) ? $_SESSION['brevointegration_token_pool'] : array();

        $candidates = $pool;
        if ($sessionToken !== '') {
            $candidates[] = $sessionToken;
        }

        foreach ($candidates as $index => $candidate) {
            $candidate = (string) $candidate;
            if ($candidate === '') {
                continue;
            }

            $isValid = function_exists('hash_equals') ? hash_equals($candidate, $token) : ($candidate === $token);
            if ($isValid) {
                if (isset($_SESSION['brevointegration_token_pool'][$index])) {
                    unset($_SESSION['brevointegration_token_pool'][$index]);
                    $_SESSION['brevointegration_token_pool'] = array_values($_SESSION['brevointegration_token_pool']);
                }

                if ($sessionToken === $candidate) {
                    $_SESSION['newtoken'] = $candidate;
                }

                return true;
            }
        }

        return false;
    }
}
