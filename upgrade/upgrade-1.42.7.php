<?php
/**
 * Upgrade script for version 1.42.7
 * Cleans up sGTM Debug / Preview Header input UI by removing placeholder example,
 * ensuring a clean and intuitive Back Office configuration experience.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_42_7($module)
{
    try {
        if (is_object($module)) {
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
