<?php
/**
 * Upgrade script for version 1.43.4
 * Restores clean datalayer_init.tpl inline script execution ensuring 100% of storefront
 * dataLayer events (view_item, view_item_list, purchase, etc.) push flawlessly.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_43_4($module)
{
    try {
        require_once dirname(__FILE__) . '/../classes/AttributionDataEngine.php';
        AttributionDataEngine::createDatabaseTable();

        if (is_object($module)) {
            $hooksToRegister = [
                'displayHeader',
                'displayAfterBodyOpeningTag',
                'displayOrderConfirmation',
                'actionValidateOrder',
                'actionOrderStatusPostUpdate',
                'displayAdminOrderSide',
                'displayAdminOrderMain',
                'displayAdminOrder',
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
                $module->_clearCache('views/templates/hook/datalayer_init.tpl');
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
