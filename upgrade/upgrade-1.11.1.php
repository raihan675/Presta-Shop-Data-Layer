<?php
/**
 * Upgrade script for version 1.11.1
 * Authoritative 4-tier list resolution for select_item across all store pages.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_11_1($module)
{
    // Clear Smarty and module cache
    if (method_exists($module, '_clearCache')) {
        $module->_clearCache('views/templates/hook/datalayer_init.tpl');
        $module->_clearCache('views/templates/hook/gtm_head.tpl');
    }
    return true;
}
