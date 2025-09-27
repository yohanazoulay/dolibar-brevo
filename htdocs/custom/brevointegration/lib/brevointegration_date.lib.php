<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Helper functions to normalise SQL datetime literals for Brevo module.
 */

if (!function_exists('brevointegration_format_sql_datetime')) {
    /**
     * Format a timestamp into a SQL literal or expression compatible with the current database handler.
     * Ensures the resulting value is quoted when the connector returns raw date strings (MySQL/MariaDB).
     *
     * @param DoliDB|mixed $db        Database handler
     * @param int          $timestamp Unix timestamp
     * @return string|null            SQL-ready datetime literal/expression or null on failure
     */
    function brevointegration_format_sql_datetime($db, $timestamp)
    {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0 || !is_object($db)) {
            return null;
        }

        $value = null;
        if (method_exists($db, 'idate')) {
            $value = $db->idate($timestamp);
            if (!is_string($value)) {
                $value = (string) $value;
            }
            $value = trim($value);
            if ($value === '') {
                $value = null;
            }
        }

        if ($value === null) {
            $value = date('Y-m-d H:i:s', $timestamp);
        }

        if ($value === null || $value === '') {
            return null;
        }

        $firstChar = $value[0];
        $lastChar = substr($value, -1);
        if ($firstChar === "'" && $lastChar === "'") {
            return $value;
        }

        $upper = strtoupper($value);
        if (strpos($value, '(') !== false || strpos($value, ')') !== false || strpos($upper, 'DATE') !== false || strpos($upper, 'TIME') !== false) {
            return $value;
        }

        return "'".$value."'";
    }
}
