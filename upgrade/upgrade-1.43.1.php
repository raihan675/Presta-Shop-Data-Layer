<?php
/**
 * Upgrade script for version 1.43.1
 * Registers displayAdminOrderSide & displayAdminOrderMain hooks and creates ps_gtm_order_attribution.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_43_1($module)
{
    try {
        require_once dirname(__FILE__) . '/../classes/AttributionDataEngine.php';
        AttributionDataEngine::createDatabaseTable();

        if (is_object($module)) {
            $hooksToRegister = [
                'displayAdminOrderSide',
                'displayAdminOrderMain',
                'displayAdminOrder',
                'displayAdminOrderTop',
                'displayAdminOrderTabOrder',
                'displayAdminOrderContentOrder',
            ];

            foreach ($hooksToRegister as $hookName) {
                if (!$module->isRegisteredInHook($hookName)) {
                    $module->registerHook($hookName);
                }
            }

            if (method_exists($module, 'clearModuleCache')) {
                $module->clearModuleCache();
            }
            if (method_exists($module, '_clearCache')) {
                $module->_clearCache('views/templates/admin/order_attribution.tpl');
                $module->_clearCache('*');
            }
        }

        if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
            PlatformAdapter::clearAllCaches();
        }
    } catch (\Throwable $e) {}

    return true;
}
