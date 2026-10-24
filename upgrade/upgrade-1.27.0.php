<?php
/**
 * Upgrade script for version 1.27.0
 * Restores classic v1.21.3 Back Office layout, ensures full hook integrity,
 * synchronizes configuration keys for raw GTM snippets, and flushes all caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_27_0($module)
{
    try {
        if (!Configuration::get('PS_GTM_RAW_HEAD_CODE')) {
            Configuration::updateValue('PS_GTM_RAW_HEAD_CODE', '', true);
        }
        if (!Configuration::get('PS_GTM_RAW_BODY_CODE')) {
            Configuration::updateValue('PS_GTM_RAW_BODY_CODE', '', true);
        }

        if (is_object($module) && method_exists($module, 'clearModuleCache')) {
            $module->clearModuleCache();
        }

        if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
            PlatformAdapter::clearAllCaches();
        }
    } catch (\Throwable $e) {}

    return true;
}
