<?php
/**
 * Upgrade script for version 1.4.4
 * Hardens add_to_cart identifier isolation and sanitizes attribute IDs.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_4_4($module)
{
    return true;
}
