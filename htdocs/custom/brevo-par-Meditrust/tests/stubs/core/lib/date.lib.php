<?php
declare(strict_types=1);

function dol_now()
{
    return time();
}

function dol_print_date($timestamp, $format)
{
    return date('Y-m-d H:i:s', (int) $timestamp);
}
