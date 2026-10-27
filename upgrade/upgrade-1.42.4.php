<?php
/**
 * Upgrade script for version 1.42.4
 * Maps OpenAI Ads view_item_list and view_search_results to custom events, restricting contents_viewed strictly to view_item.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_42_4($module)
{
    try {
        // Clear module and template caches to force reload of updated assets
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
