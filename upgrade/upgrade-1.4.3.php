<?php
/**
 * Upgrade script for version 1.4.3
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_4_3($module)
{
    return $module->registerHook('displayBackOfficeTop')
        && $module->registerHook('displayBackOfficeHeader');
}
