<?php
/**
 * Upgrade script for version 1.42.6
 * Re-introduces sGTM Debug / Preview Header (X-Gtm-Server-Preview) support,
 * registers actionOrderStatusUpdate hook for exact pre-update state tracking,
 * and adds live Back Office Test Order Status Webhook dispatch capability.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_42_6($module)
{
    try {
        if (class_exists('Configuration')) {
            if (Configuration::get('PS_SGTM_PREVIEW_HEADER') === false) {
                Configuration::updateValue('PS_SGTM_PREVIEW_HEADER', '');
            }
        }

        if (is_object($module)) {
            if (!$module->isRegisteredInHook('actionOrderStatusUpdate')) {
                $module->registerHook('actionOrderStatusUpdate');
            }
            if (!$module->isRegisteredInHook('actionOrderStatusPostUpdate')) {
                $module->registerHook('actionOrderStatusPostUpdate');
            }

            if (method_exists($module, 'clearModuleCache')) {
                $module->clearModuleCache();
            }
            if (method_exists($module, '_clearCache')) {
                $module->_clearCache('views/templates/admin/configuration.tpl');
                $module->_clearCache('*');
            }
        }

        if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
            PlatformAdapter::clearAllCaches();
        }
    } catch (\Throwable $e) {}

    return true;
}
