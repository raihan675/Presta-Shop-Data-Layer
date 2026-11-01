<?php
/**
 * PrestaShop DataLayer - Upgrade Script 1.45.5
 *
 * Synchronizes authoritative variant pricing and flushes storefront Smarty caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_45_5($module)
{
    try {
        $adapterPath = dirname(__FILE__) . '/../classes/PlatformAdapter.php';
        if (file_exists($adapterPath)) {
            require_once $adapterPath;
            if (class_exists('PlatformAdapter')) {
                PlatformAdapter::syncModuleDatabaseVersion($module->name, $module->version);
                PlatformAdapter::clearAllCaches();
            }
        }

        if (method_exists($module, '_clearCache')) {
            $module->_clearCache('views/templates/hook/gtm_head.tpl');
            $module->_clearCache('views/templates/hook/datalayer_init.tpl');
        }
    } catch (\Throwable $e) {
        // Defensive non-blocking execution
    }

    return true;
}
