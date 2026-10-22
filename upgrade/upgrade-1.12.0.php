<?php
/**
 * Upgrade script for version 1.12.0
 * Registers actionCarrierProcess hook and initializes checkout step observers.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_12_0($module)
{
    if (!$module->isRegisteredInHook('actionCarrierProcess')) {
        $module->registerHook('actionCarrierProcess');
    }

    if (method_exists($module, '_clearCache')) {
        $module->_clearCache('views/templates/hook/datalayer_init.tpl');
        $module->_clearCache('views/templates/hook/gtm_head.tpl');
    }

    return true;
}
