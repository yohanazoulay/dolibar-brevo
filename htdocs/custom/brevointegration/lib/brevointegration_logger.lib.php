<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Lightweight file logger dedicated to Brevo admin diagnostics.
 */

if (!function_exists('brevointegration_logger_get_log_directory')) {
    /**
     * Resolve the directory where Brevo diagnostic logs should be stored.
     *
     * @return string
     */
    function brevointegration_logger_get_log_directory()
    {
        $baseDir = '';

        if (defined('DOL_DATA_ROOT')) {
            $baseDir = rtrim((string) DOL_DATA_ROOT, '/');
        }

        if ($baseDir === '') {
            $baseDir = rtrim(sys_get_temp_dir(), '/');
        }

        $logDir = $baseDir.'/brevointegration';

        if (!function_exists('dol_mkdir')) {
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }

            return $logDir;
        }

        if (!is_dir($logDir)) {
            dol_mkdir($logDir);
        }

        return $logDir;
    }
}

if (!function_exists('brevointegration_logger_get_log_file_path')) {
    /**
     * Return the absolute path to the Brevo admin log file.
     *
     * @return string
     */
    function brevointegration_logger_get_log_file_path()
    {
        $directory = brevointegration_logger_get_log_directory();
        if ($directory === '') {
            return '';
        }

        return $directory.'/brevo_admin.log';
    }
}

if (!function_exists('brevointegration_logger_write')) {
    /**
     * Write an entry to the Brevo admin log file.
     *
     * @param string $level   Log level label
     * @param string $message Message to log
     * @param array  $context Additional contextual data
     * @return void
     */
    function brevointegration_logger_write($level, $message, array $context = array())
    {
        $path = brevointegration_logger_get_log_file_path();
        if ($path === '') {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');

        $contextPart = '';
        if (!empty($context)) {
            $contextPart = ' '.brevointegration_logger_encode_context($context);
        }

        $entry = sprintf('[%s] [%s] %s%s%s', $timestamp, strtoupper((string) $level), (string) $message, $contextPart, PHP_EOL);

        try {
            file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
        } catch (Throwable $exception) {
            if (function_exists('dol_syslog')) {
                dol_syslog(__METHOD__.' unable to write log file: '.$exception->getMessage(), LOG_WARNING);
            }
        }
    }
}

if (!function_exists('brevointegration_logger_encode_context')) {
    /**
     * Safely encode context data for the log file.
     *
     * @param array $context
     * @return string
     */
    function brevointegration_logger_encode_context(array $context)
    {
        $normalized = array();
        foreach ($context as $key => $value) {
            if ($value instanceof Throwable) {
                $normalized[$key] = array(
                    'type' => get_class($value),
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                    'file' => $value->getFile(),
                    'line' => $value->getLine(),
                );
                continue;
            }

            if (is_resource($value)) {
                $normalized[$key] = sprintf('resource(%s)', get_resource_type($value));
                continue;
            }

            $normalized[$key] = $value;
        }

        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return '[context_encoding_error]';
        }

        return $json;
    }
}

if (!function_exists('brevointegration_logger_debug')) {
    /**
     * Log a debug level message.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    function brevointegration_logger_debug($message, array $context = array())
    {
        brevointegration_logger_write('DEBUG', $message, $context);
    }
}

if (!function_exists('brevointegration_logger_info')) {
    /**
     * Log an info level message.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    function brevointegration_logger_info($message, array $context = array())
    {
        brevointegration_logger_write('INFO', $message, $context);
    }
}

if (!function_exists('brevointegration_logger_error')) {
    /**
     * Log an error level message.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    function brevointegration_logger_error($message, array $context = array())
    {
        brevointegration_logger_write('ERROR', $message, $context);
    }
}
