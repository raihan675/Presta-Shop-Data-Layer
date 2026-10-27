<?php
/**
 * Upgrade script for version 1.41.2
 * Flushes admin template cache after removing the OpenAI Ads informational subcard.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_41_2($module)
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
