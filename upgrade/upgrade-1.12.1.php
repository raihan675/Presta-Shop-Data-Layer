<?php
/**
 * Upgrade script for version 1.12.1
 * Enhances universal remove_from_cart tracking across Checkout (One-Page-Checkout), Cart, and Mini-Cart,
 * and fixes false select_item triggering on cart action / removal clicks.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_12_1($module)
{
    if (method_exists($module, '_clearCache')) {
        $module->_clearCache('views/templates/hook/datalayer_init.tpl');
        $module->_clearCache('views/templates/hook/gtm_head.tpl');
    }

    return true;
}
